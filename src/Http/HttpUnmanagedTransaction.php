<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Http;

use function array_intersect;
use function array_unique;

use Laudis\Neo4j\Common\TransactionHelper;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherList;

use function microtime;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use stdClass;

/**
 * @template T
 *
 * @implements UnmanagedTransactionInterface<T>
 */
final class HttpUnmanagedTransaction implements UnmanagedTransactionInterface
{
    private bool $isCommitted = false;

    private bool $isRolledBack = false;

    /**
     * @psalm-mutation-free
     *
     * @param FormatterInterface<T> $formatter
     */
    public function __construct(
        /** @psalm-readonly */
        private readonly RequestInterface $request,
        /** @psalm-readonly */
        private readonly HttpConnection $connection,
        /** @psalm-readonly */
        private readonly StreamFactoryInterface $factory,
        /**
         * @psalm-readonly
         */
        private readonly FormatterInterface $formatter
    ) {}

    public function run(string $statement, iterable $parameters = [])
    {
        return $this->runStatement(new Statement($statement, $parameters));
    }

    public function runStatement(Statement $statement)
    {
        return $this->runStatements([$statement])->first();
    }

    public function runStatements(iterable $statements): CypherList
    {
        $request = $this->request->withMethod('POST');

        $body = HttpHelper::statementsToJson($this->connection, $this->formatter, $statements);

        $request = $request->withBody($this->factory->createStream($body));
        $start = microtime(true);
        $response = $this->connection->getImplementation()->sendRequest($request);
        $total = microtime(true) - $start;

        $data = $this->handleResponse($response);

        return $this->formatter->formatHttpResult($response, $data, $this->connection, $total, $total, $statements);
    }

    public function commit(iterable $statements = []): CypherList
    {
        $uri = $this->request->getUri();
        $request = $this->request->withUri($uri->withPath($uri->getPath().'/commit'))->withMethod('POST');

        $content = HttpHelper::statementsToJson($this->connection, $this->formatter, $statements);
        $request = $request->withBody($this->factory->createStream($content));

        $start = microtime(true);
        $response = $this->connection->getImplementation()->sendRequest($request);
        $total = microtime(true) - $start;

        $data = $this->handleResponse($response);

        $this->isCommitted = true;

        return $this->formatter->formatHttpResult($response, $data, $this->connection, $total, $total, $statements);
    }

    public function rollback(): void
    {
        $request = $this->request->withMethod('DELETE');
        $response = $this->connection->getImplementation()->sendRequest($request);

        $this->handleResponse($response);

        $this->isRolledBack = true;
    }

    public function __destruct()
    {
        $this->connection->close();
    }

    public function isRolledBack(): bool
    {
        return $this->isRolledBack;
    }

    public function isCommitted(): bool
    {
        return $this->isCommitted;
    }

    public function isFinished(): bool
    {
        return $this->isRolledBack() || $this->isCommitted();
    }

    /**
     * @throws Neo4jException
     *
     * @return never
     */
    private function handleNeo4jException(Neo4jException $e): void
    {
        if (!$this->isFinished()) {
            $classifications = array_map(static fn (Neo4jError $e) => $e->getClassification(), $e->getErrors());
            $classifications = array_unique($classifications);

            $intersection = array_intersect($classifications, TransactionHelper::ROLLBACK_CLASSIFICATIONS);
            if ($intersection !== []) {
                $this->isRolledBack = true;
            }
        }

        throw $e;
    }

    /**
     * @throws Neo4jException
     */
    private function handleResponse(ResponseInterface $response): stdClass
    {
        try {
            $data = HttpHelper::interpretResponse($response);
        } catch (Neo4jException $e) {
            $this->handleNeo4jException($e);
        }

        return $data;
    }
}
