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
use Laudis\Neo4j\Common\TransactionHelper;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Types\CypherList;
use function parse_url;
use const PHP_URL_PATH;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * @template T
 *
 * @implements SessionInterface<T>
 */
final class HttpSession implements SessionInterface
{
    private SessionConfiguration $config;
    private StreamFactoryInterface $streamFactory;
    private HttpConnectionPool $pool;
    /** @var FormatterInterface<T> */
    private FormatterInterface $formatter;
    private RequestFactory $requestFactory;
    private string $uri;

    /**
     * @param FormatterInterface<T> $formatter
     */
    public function __construct(
        StreamFactoryInterface $factory,
        HttpConnectionPool $manager,
        SessionConfiguration $config,
        FormatterInterface $formatter,
        RequestFactory $requestFactory,
        string $uri
    ) {
        $this->streamFactory = $factory;
        $this->config = $config;
        $this->pool = $manager;
        $this->formatter = $formatter;
        $this->requestFactory = $requestFactory;
        $this->uri = $uri;
    }

    /**
     * @throws ClientExceptionInterface|JsonException
     */
    public function runStatements(iterable $statements, ?TransactionConfiguration $config = null): CypherList
    {
        $request = $this->requestFactory->createRequest('POST', $this->uri);
        $client = $this->pool->acquire($request->getUri(), $this->config->getAccessMode());
        $content = HttpHelper::statementsToString($this->formatter, $statements);
        $request = $this->instantCommitRequest($request)->withBody($this->streamFactory->createStream($content));

        $response = $client->sendRequest($request);

        $data = HttpHelper::interpretResponse($response);

        return $this->formatter->formatHttpResult($response, $data);
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function openTransaction(iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        return $this->beginTransaction($statements, $config);
    }

    public function writeTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return TransactionHelper::retry(
            fn () => $this->openTransaction(),
            $tsxHandler,
            $config ?? TransactionConfiguration::default()
        );
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
     * @throws ClientExceptionInterface|JsonException
     */
    public function runStatement(Statement $statement, ?TransactionConfiguration $config = null)
    {
        return $this->runStatements([$statement], $config)->first();
    }

    /**
     * @throws ClientExceptionInterface|JsonException
     */
    public function run(string $statement, iterable $parameters = [], ?TransactionConfiguration $config = null)
    {
        return $this->runStatement(Statement::create($statement, $parameters), $config);
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function beginTransaction(?iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        $request = $this->requestFactory->createRequest('POST', $this->uri);
        $request->getBody()->write(HttpHelper::statementsToString($this->formatter, $statements ?? []));
        $client = $this->pool->acquire($request->getUri(), $this->config->getAccessMode());
        $response = $client->sendRequest($request);

        /** @var array{commit: string} $data */
        $data = HttpHelper::interpretResponse($response);

        $path = str_replace('/commit', '', parse_url($data['commit'], PHP_URL_PATH));
        $uri = $request->getUri()->withPath($path);
        $request = $request->withUri($uri);

        return $this->makeTransaction($client, $request);
    }

    /**
     * @return HttpUnmanagedTransaction<T>
     */
    private function makeTransaction(ClientInterface $client, RequestInterface $request): HttpUnmanagedTransaction
    {
        return new HttpUnmanagedTransaction(
            $request,
            $client,
            $this->streamFactory,
            $this->formatter
        );
    }

    private function instantCommitRequest(RequestInterface $request): RequestInterface
    {
        $path = $request->getUri()->getPath().'/commit';
        $uri = $request->getUri()->withPath($path);

        return $request->withUri($uri);
    }
}
