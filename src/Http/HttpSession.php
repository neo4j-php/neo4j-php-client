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
use Laudis\Neo4j\Common\Resolvable;
use Laudis\Neo4j\Common\TransactionHelper;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Types\CypherList;
use function microtime;
use function parse_url;
use const PHP_URL_PATH;
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
    /** @psalm-readonly */
    private SessionConfiguration $config;
    /**
     * @psalm-readonly
     *
     * @var Resolvable<StreamFactoryInterface>
     */
    private Resolvable $streamFactory;
    /** @psalm-readonly */
    private HttpConnectionPool $pool;
    /**
     * @psalm-readonly
     *
     * @var FormatterInterface<T>
     */
    private FormatterInterface $formatter;
    /**
     * @psalm-readonly
     *
     * @var Resolvable<RequestFactory>
     */
    private Resolvable $requestFactory;
    /**
     * @psalm-readonly
     *
     * @var Resolvable<string>
     */
    private Resolvable $uri;
    /** @psalm-readonly */
    private AuthenticateInterface $auth;
    /** @psalm-readonly */
    private string $userAgent;

    /**
     * @psalm-mutation-free
     *
     * @param FormatterInterface<T>              $formatter
     * @param Resolvable<StreamFactoryInterface> $factory
     * @param Resolvable<string>                 $uri
     * @param Resolvable<RequestFactory>         $requestFactory
     */
    public function __construct(
        Resolvable $factory,
        HttpConnectionPool $manager,
        SessionConfiguration $config,
        FormatterInterface $formatter,
        Resolvable $requestFactory,
        Resolvable $uri,
        AuthenticateInterface $auth,
        string $userAgent
    ) {
        $this->streamFactory = $factory;
        $this->config = $config;
        $this->pool = $manager;
        $this->formatter = $formatter;
        $this->requestFactory = $requestFactory;
        $this->uri = $uri;
        $this->auth = $auth;
        $this->userAgent = $userAgent;
    }

    /**
     * @throws JsonException
     */
    public function runStatements(iterable $statements, ?TransactionConfiguration $config = null): CypherList
    {
        $request = $this->requestFactory->resolve()->createRequest('POST', $this->uri->resolve());
        $connection = $this->pool->acquire($request->getUri(), $this->auth, $this->config);
        $content = HttpHelper::statementsToJson($this->formatter, $statements);
        $request = $this->instantCommitRequest($request)->withBody($this->streamFactory->resolve()->createStream($content));

        $start = microtime(true);
        $response = $connection->getImplementation()->sendRequest($request);
        $time = microtime(true) - $start;

        $data = HttpHelper::interpretResponse($response);

        return $this->formatter->formatHttpResult($response, $data, $connection, $time, $time, $statements);
    }

    /**
     * @throws JsonException
     */
    public function openTransaction(iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        return $this->beginTransaction($statements, $config);
    }

    public function writeTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return TransactionHelper::retry(fn () => $this->openTransaction(), $tsxHandler);
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
        $request->getBody()->write(HttpHelper::statementsToJson($this->formatter, $statements ?? []));
        $connection = $this->pool->acquire($request->getUri(), $this->auth, $this->config);
        $response = $connection->getImplementation()->sendRequest($request);

        /** @var string */
        $url = HttpHelper::interpretResponse($response)->commit;
        $path = str_replace('/commit', '', parse_url($url, PHP_URL_PATH));
        $uri = $request->getUri()->withPath($path);
        $request = $request->withUri($uri);

        return $this->makeTransaction($connection, $request);
    }

    /**
     * @param ConnectionInterface<ClientInterface> $connection
     *
     * @return HttpUnmanagedTransaction<T>
     */
    private function makeTransaction(ConnectionInterface $connection, RequestInterface $request): HttpUnmanagedTransaction
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
}
