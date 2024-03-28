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

use JsonException;
use Laudis\Neo4j\Common\GeneratorHelper;
use Laudis\Neo4j\Common\Resolvable;
use Laudis\Neo4j\Common\TransactionHelper;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Types\CypherList;

use function microtime;
use function parse_url;

use const PHP_URL_PATH;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use stdClass;

/**
 * @template T
 *
 * @implements SessionInterface<T>
 */
final class HttpSession implements SessionInterface
{
    /**
     * @psalm-mutation-free
     *
     * @param Resolvable<StreamFactoryInterface> $streamFactory
     * @param FormatterInterface<T>              $formatter
     * @param Resolvable<RequestFactory>         $requestFactory
     * @param Resolvable<string>                 $uri
     */
    public function __construct(
        /**
         * @psalm-readonly
         */
        private readonly Resolvable $streamFactory,
        /** @psalm-readonly */
        private readonly HttpConnectionPool $pool,
        /** @psalm-readonly */
        private readonly SessionConfiguration $config,
        /**
         * @psalm-readonly
         */
        private readonly FormatterInterface $formatter,
        /**
         * @psalm-readonly
         */
        private readonly Resolvable $requestFactory,
        /**
         * @psalm-readonly
         */
        private readonly Resolvable $uri,
        AuthenticateInterface $auth,
        string $userAgent
    ) {}

    /**
     * @throws JsonException
     */
    public function runStatements(iterable $statements, ?TransactionConfiguration $config = null): CypherList
    {
        $request = $this->requestFactory->resolve()->createRequest('POST', $this->uri->resolve());
        $connection = $this->pool->acquire($this->config);
        /** @var HttpConnection */
        $connection = GeneratorHelper::getReturnFromGenerator($connection);
        $content = HttpHelper::statementsToJson($connection, $this->formatter, $statements);
        $request = $this->formatter->decorateRequest($request, $connection);
        $request = $this->instantCommitRequest($request)->withBody($this->streamFactory->resolve()->createStream($content));

        $start = microtime(true);
        $response = $connection->getImplementation()->sendRequest($request);
        $time = microtime(true) - $start;

        $data = HttpHelper::interpretResponse($response);

        return $this->formatter->formatHttpResult($response, $data, $connection, $time, $time, $statements);
    }

    public function writeTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return TransactionHelper::retry(fn () => $this->beginTransaction(), $tsxHandler);
    }

    public function readTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return $this->writeTransaction($tsxHandler, $config);
    }

    public function transaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        if ($this->config->getAccessMode() === AccessMode::WRITE()) {
            return $this->writeTransaction($tsxHandler, $config);
        }

        return $this->readTransaction($tsxHandler, $config);
    }

    /**
     * @throws JsonException
     */
    public function runStatement(Statement $statement, ?TransactionConfiguration $config = null)
    {
        return $this->runStatements([$statement], $config)->first();
    }

    /**
     * @throws JsonException
     */
    public function run(string $statement, iterable $parameters = [], ?TransactionConfiguration $config = null)
    {
        return $this->runStatement(Statement::create($statement, $parameters), $config);
    }

    /**
     * @throws JsonException
     */
    public function beginTransaction(?iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        $request = $this->requestFactory->resolve()->createRequest('POST', $this->uri->resolve());
        $connection = $this->pool->acquire($this->config);
        /** @var HttpConnection */
        $connection = GeneratorHelper::getReturnFromGenerator($connection);

        $request = $this->formatter->decorateRequest($request, $connection);
        $request->getBody()->write(HttpHelper::statementsToJson($connection, $this->formatter, $statements ?? []));
        $response = $connection->getImplementation()->sendRequest($request);

        $response = HttpHelper::interpretResponse($response);
        if (isset($response->info) && $response->info instanceof stdClass) {
            /** @var string */
            $url = $response->info->commit;
        } else {
            /** @var string */
            $url = $response->commit;
        }
        $path = str_replace('/commit', '', parse_url($url, PHP_URL_PATH));
        $uri = $request->getUri()->withPath($path);
        $request = $request->withUri($uri);

        return $this->makeTransaction($connection, $request);
    }

    /**
     * @return HttpUnmanagedTransaction<T>
     */
    private function makeTransaction(HttpConnection $connection, RequestInterface $request): HttpUnmanagedTransaction
    {
        return new HttpUnmanagedTransaction(
            $request,
            $connection,
            $this->streamFactory->resolve(),
            $this->formatter
        );
    }

    private function instantCommitRequest(RequestInterface $request): RequestInterface
    {
        $path = $request->getUri()->getPath().'/commit';
        $uri = $request->getUri()->withPath($path);

        return $request->withUri($uri);
    }

    public function getLastBookmark(): Bookmark
    {
        return new Bookmark([]);
    }
}
