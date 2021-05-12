<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\HttpDriver;

use function array_merge_recursive;
use Ds\Vector;
use function json_encode;
use function var_export;
use const JSON_THROW_ON_ERROR;
use JsonException;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\StaticTransactionConfiguration;
use Laudis\Neo4j\ParameterHelper;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use stdClass;

/**
 * @template T
 *
 * @implements UnmanagedTransactionInterface<T>
 */
final class HttpUnmanagedTransaction implements UnmanagedTransactionInterface
{
    private RequestInterface $request;
    private StreamFactoryInterface $factory;
    /** @var StaticTransactionConfiguration<T> */
    private StaticTransactionConfiguration $config;
    private ClientInterface $client;

    /**
     * @param StaticTransactionConfiguration<T> $config
     */
    public function __construct(
        RequestInterface $request,
        ClientInterface $client,
        StreamFactoryInterface $factory,
        StaticTransactionConfiguration $config
    ) {
        $this->request = $request;
        $this->factory = $factory;
        $this->config = $config;
        $this->client = $client;
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function run(string $statement, iterable $parameters = [])
    {
        return $this->runStatement(new Statement($statement, $parameters));
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function runStatement(Statement $statement)
    {
        return $this->runStatements([$statement])->first();
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function runStatements(iterable $statements): Vector
    {
        $request = $this->request->withMethod('POST');

        $body = $this->statementsToString($statements);

        $request = $request->withBody($this->factory->createStream($body));
        $response = $this->client->sendRequest($request);
        $data = HttpHelper::interpretResponse($response);

        return $this->config->getFormatter()->formatHttpResult($response, $data);
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function commit(iterable $statements = []): Vector
    {
        $uri = $this->request->getUri();
        $request = $this->request->withUri($uri->withPath($uri->getPath().'/commit'))->withMethod('POST');
        $request = $request->withBody($this->factory->createStream($this->statementsToString($statements)));

        $response = $this->client->sendRequest($request);

        $data = HttpHelper::interpretResponse($response);

        return $this->config->getFormatter()->formatHttpResult($response, $data);
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function rollback(): void
    {
        $request = $this->request->withMethod('DELETE');
        $response = $this->client->sendRequest($request);

        HttpHelper::interpretResponse($response);
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @throws JsonException
     */
    private function statementsToString(iterable $statements): string
    {
        $tbr = [];
        foreach ($statements as $statement) {
            $st = [
                'statement' => $statement->getText(),
                'resultDataContents' => [],
                'includeStats' => false,
            ];
            $st = array_merge($st, $this->config->getFormatter()->statementConfigOverride());
            $parameters = ParameterHelper::formatParameters($statement->getParameters());
            $st['parameters'] = $parameters->count() === 0 ? new stdClass() : $parameters->toArray();
            $tbr[] = $st;
        }

        return json_encode([
            'statements' => $tbr,
        ], JSON_THROW_ON_ERROR);
    }

    public function getConfiguration(): StaticTransactionConfiguration
    {
        return $this->config;
    }

    public function withTimeout($timeout): TransactionInterface
    {
        return new self(
            $this->request,
            $this->client,
            $this->factory,
            $this->config->withTimeout($timeout)
        );
    }

    public function withFormatter($formatter): TransactionInterface
    {
        return new self(
            $this->request,
            $this->client,
            $this->factory,
            $this->config->withFormatter($formatter)
        );
    }

    public function withMetaData($metaData): TransactionInterface
    {
        return new self(
            $this->request,
            $this->client,
            $this->factory,
            $this->config->withMetaData($metaData)
        );
    }
}
