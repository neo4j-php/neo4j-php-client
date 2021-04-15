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

namespace Laudis\Neo4j\Network\Http;

use Ds\Vector;
use Exception;
use JsonException;
use Laudis\Neo4j\ConnectionManager;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\StaticTransactionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\HttpDriver\HttpHelper;
use Laudis\Neo4j\HttpDriver\HttpUnmanagedTransaction;
use function parse_url;
use const PHP_URL_PATH;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * @template T
 *
 * @implements SessionInterface<T>
 *
 * @psalm-import-type CypherResponseSet from \Laudis\Neo4j\Contracts\FormatterInterface
 */
final class HttpSession implements SessionInterface
{
    private RequestInterface $request;
    /** @var HttpDriver<T> */
    private HttpDriver $driver;
    private SessionConfiguration $config;
    private StreamFactoryInterface $factory;

    /**
     * @param HttpDriver<T> $driver
     */
    public function __construct(RequestInterface $request, StreamFactoryInterface $factory, HttpDriver $driver, SessionConfiguration $config)
    {
        $this->request = $request;
        $this->factory = $factory;
        $this->driver = $driver;
        $this->config = $config;
    }

    /**
     * @throws Exception|ClientExceptionInterface
     */
    public function runStatements(iterable $statements, ?TransactionConfiguration $config = null): Vector
    {
        return $this->makeTransaction($this->request->getUri(), $config)->runStatements($statements);
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
        return ConnectionManager::retry(
            fn () => $this->openTransaction(),
            $tsxHandler,
            $this->driver->getTransactionConfiguration()->merge($config)
        );
    }

    public function readTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return $this->writeTransaction($tsxHandler, $config);
    }

    public function transaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return $this->writeTransaction($tsxHandler, $config);
    }

    public function getConfig(): SessionConfiguration
    {
        return $this->config;
    }

    /**
     * @throws Exception|ClientExceptionInterface
     */
    public function runStatement(Statement $statement, ?TransactionConfiguration $config = null)
    {
        return $this->runStatements([$statement], $config)->first();
    }

    /**
     * @throws Exception|ClientExceptionInterface
     */
    public function run(string $statement, iterable $parameters, ?TransactionConfiguration $config = null)
    {
        return $this->runStatement(Statement::create($statement, $parameters), $config);
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function beginTransaction(?iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        $response = $this->driver->acquireConnection($this->config)->sendRequest($this->request->withMethod('POST'));
        /** @var array{commit: string} $data */
        $data = HttpHelper::interpretResponse($response);

        $path = str_replace('/commit', '', parse_url($data['commit'], PHP_URL_PATH));
        $uri = $this->request->getUri()->withPath($path);

        return $this->makeTransaction($uri, $config);
    }

    public function getTransactionConfig(): StaticTransactionConfiguration
    {
        return $this->driver->getTransactionConfiguration();
    }

    public function withFormatter($formatter): SessionInterface
    {
        return new self($this->request, $this->factory, $this->driver->withFormatter($formatter), $this->config);
    }

    /**
     * @return HttpUnmanagedTransaction<T>
     */
    private function makeTransaction(UriInterface $uri, ?TransactionConfiguration $config): HttpUnmanagedTransaction
    {
        $tsxConfig = $this->getTransactionConfig()->merge($config);

        return new HttpUnmanagedTransaction(
            $this->request->withUri($uri),
            $this->driver->acquireConnection($this->config),
            $this->factory,
            $tsxConfig
        );
    }

    public function withTransactionTimeout($timeout): SessionInterface
    {
        return new self($this->request, $this->factory, $this->driver->withTransactionTimeout($timeout), $this->config);
    }

    public function withDatabase($database): SessionInterface
    {
        return new self($this->request, $this->factory, $this->driver, $this->config->withDatabase($database));
    }

    public function withFetchSize($fetchSize): SessionInterface
    {
        return new self($this->request, $this->factory, $this->driver->withFetchSize($fetchSize), $this->config);
    }

    public function withAccessMode($accessMode): SessionInterface
    {
        return new self($this->request, $this->factory, $this->driver->withAccessMode($accessMode), $this->config);
    }

    public function withBookmarks($bookmarks): SessionInterface
    {
        return new self($this->request, $this->factory, $this->driver, $this->config->withBookmarks($bookmarks));
    }

    public function withConfiguration(SessionConfiguration $configuration): SessionInterface
    {
        return new self($this->request, $this->factory, $this->driver, $configuration);
    }

    public function withTransactionConfiguration($configuration): SessionInterface
    {
        $driver = $this->driver->withTransactionConfiguration($configuration);

        return new self($this->request, $this->factory, $driver, $this->config);
    }
}
