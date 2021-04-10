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
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfig;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\HttpDriver\HttpHelper;
use Laudis\Neo4j\HttpDriver\HttpTransaction;
use function microtime;
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
    /** @var FormatterInterface<T> */
    private FormatterInterface $formatter;
    private RequestInterface $request;
    private HttpDriver $driver;
    private SessionConfiguration $config;
    private StreamFactoryInterface $factory;

    /**
     * @param FormatterInterface<T> $formatter
     */
    public function __construct(RequestInterface $request, FormatterInterface $formatter, HttpDriver $driver, SessionConfiguration $config, StreamFactoryInterface $factory)
    {
        $this->request = $request;
        $this->formatter = $formatter;
        $this->driver = $driver;
        $this->config = $config;
        $this->factory = $factory;
    }

    /**
     * @throws Exception|ClientExceptionInterface
     */
    public function runStatements(iterable $statements, ?TransactionConfig $config = null): Vector
    {
        return $this->makeTransaction($config, $this->request->getUri())->runStatements($statements);
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function openTransaction(iterable $statements = null, ?TransactionConfig $config = null): TransactionInterface
    {
        return $this->beginTransaction($statements, $config);
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function writeTransaction(callable $tsxHandler, ?TransactionConfig $config = null)
    {
        return $this->retry($tsxHandler, $config ?? TransactionConfig::default());
    }

    /**
     * @template U
     *
     * @param callable(\Laudis\Neo4j\Contracts\ManagedTransactionInterface<T>):U $tsxHandler
     *
     * @throws JsonException|ClientExceptionInterface
     *
     * @return U
     */
    private function retry(callable $tsxHandler, TransactionConfig $config)
    {
        $timeout = $config->getTimeout();
        if ($timeout) {
            $limit = microtime(true) + $timeout;
        } else {
            $limit = PHP_FLOAT_MAX;
        }
        while (true) {
            try {
                $transaction = $this->openTransaction();
                $tbr = $tsxHandler($transaction);
                $transaction->commit();

                return $tbr;
            } catch (Neo4jException $e) {
                if (microtime(true) > $limit) {
                    throw $e;
                }
            }
        }
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function readTransaction(callable $tsxHandler, ?TransactionConfig $config = null)
    {
        return $this->writeTransaction($tsxHandler, $config);
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function transaction(callable $tsxHandler, ?TransactionConfig $config = null)
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
    public function runStatement(Statement $statement, ?TransactionConfig $config = null)
    {
        return $this->runStatements([$statement], $config)->first();
    }

    /**
     * @throws Exception|ClientExceptionInterface
     */
    public function run(string $statement, iterable $parameters, ?TransactionConfig $config = null)
    {
        return $this->runStatement(Statement::create($statement, $parameters), $config);
    }

    /**
     * @throws JsonException|ClientExceptionInterface
     */
    public function beginTransaction(?iterable $statements = null, ?TransactionConfig $config = null): TransactionInterface
    {
        $config ??= TransactionConfig::default();

        $response = $this->driver->acquireConnection($this->config, $config)->sendRequest($this->request->withMethod('POST'));
        /** @var array{commit: string} $data */
        $data = HttpHelper::interpretResponse($response);

        $path = str_replace('/commit', '', parse_url($data['commit'], PHP_URL_PATH));
        $uri = $this->request->getUri()->withPath($path);

        return $this->makeTransaction($config, $uri);
    }

    public function getFormatter(): FormatterInterface
    {
        return $this->formatter;
    }

    public function withFormatter(FormatterInterface $formatter): SessionInterface
    {
        return new self($this->request, $formatter, $this->driver, $this->config, $this->factory);
    }

    /**
     * @return HttpTransaction<T>
     */
    private function makeTransaction(?TransactionConfig $config, UriInterface $uri): HttpTransaction
    {
        return new HttpTransaction(
            $this->request->withUri($uri),
            $this->formatter,
            $this->driver,
            $this->factory,
            $this->config,
            $config ?? TransactionConfig::default()
        );
    }
}
