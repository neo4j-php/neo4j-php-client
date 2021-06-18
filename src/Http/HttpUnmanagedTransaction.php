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

namespace Laudis\Neo4j\Http;

use JsonException;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Types\CypherList;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @template T
 *
 * @implements UnmanagedTransactionInterface<T>
 */
final class HttpUnmanagedTransaction implements UnmanagedTransactionInterface
{
    private RequestInterface $request;
    private StreamFactoryInterface $factory;
    private ClientInterface $client;
    /** @var FormatterInterface<T> */
    private FormatterInterface $formatter;

    /**
     * @param FormatterInterface<T> $formatter
     */
    public function __construct(
        RequestInterface $request,
        ClientInterface $client,
        StreamFactoryInterface $factory,
        FormatterInterface $formatter
    ) {
        $this->request = $request;
        $this->factory = $factory;
        $this->client = $client;
        $this->formatter = $formatter;
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
    public function runStatements(iterable $statements): CypherList
    {
        $request = $this->request->withMethod('POST');

        $body = HttpHelper::statementsToString($this->formatter, $statements);

        $request = $request->withBody($this->factory->createStream($body));
        $response = $this->client->sendRequest($request);
        $data = HttpHelper::interpretResponse($response);

        return $this->formatter->formatHttpResult($response, $data);
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function commit(iterable $statements = []): CypherList
    {
        $uri = $this->request->getUri();
        $request = $this->request->withUri($uri->withPath($uri->getPath().'/commit'))->withMethod('POST');
        $content = HttpHelper::statementsToString($this->formatter, $statements);
        $request = $request->withBody($this->factory->createStream($content));

        $response = $this->client->sendRequest($request);

        $data = HttpHelper::interpretResponse($response);

        return $this->formatter->formatHttpResult($response, $data);
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
}
