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

namespace Laudis\Neo4j\Bolt;

use Bolt\error\MessageException;
use Exception;
use Laudis\Neo4j\Common\BoltConnection;
use Laudis\Neo4j\Common\TransactionHelper;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Neo4j\Neo4jConnectionPool;
use Laudis\Neo4j\Types\CypherList;
use Psr\Http\Message\UriInterface;

/**
 * A session using bolt connections.
 *
 * @template ResultFormat
 *
 * @implements SessionInterface<ResultFormat>
 */
final class Session implements SessionInterface
{
    /** @psalm-readonly  */
    private SessionConfiguration $config;
    /**
     * @psalm-readonly
     *
     * @var BoltConnectionPool|Neo4jConnectionPool
     */
    private ConnectionPoolInterface $pool;
    /**
     * @psalm-readonly
     *
     * @var FormatterInterface<ResultFormat>
     */
    private FormatterInterface $formatter;
    /** @psalm-readonly */
    private UriInterface $uri;
    /** @psalm-readonly  */
    private AuthenticateInterface $auth;

    /**
     * @param FormatterInterface<ResultFormat>       $formatter
     * @param BoltConnectionPool|Neo4jConnectionPool $pool
     *
     * @psalm-mutation-free
     */
    public function __construct(
        SessionConfiguration $config,
        ConnectionPoolInterface $pool,
        FormatterInterface $formatter,
        UriInterface $uri,
        AuthenticateInterface $auth
    ) {
        $this->config = $config;
        $this->pool = $pool;
        $this->formatter = $formatter;
        $this->uri = $uri;
        $this->auth = $auth;
    }

    public function runStatements(iterable $statements, ?TransactionConfiguration $config = null): CypherList
    {
        $tbr = [];

        $config = $this->mergeTsxConfig($config);
        foreach ($statements as $statement) {
            $tbr[] = $this->beginInstantTransaction($this->config, $config)->runStatement($statement);
        }

        return new CypherList($tbr);
    }

    public function openTransaction(iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        return $this->beginTransaction($statements, $this->mergeTsxConfig($config));
    }

    public function runStatement(Statement $statement, ?TransactionConfiguration $config = null)
    {
        return $this->runStatements([$statement], $config)->first();
    }

    public function run(string $statement, iterable $parameters = [], ?TransactionConfiguration $config = null)
    {
        return $this->runStatement(new Statement($statement, $parameters), $config);
    }

    public function writeTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        $config = $this->mergeTsxConfig($config);

        return TransactionHelper::retry(
            fn () => $this->startTransaction($config, $this->config->withAccessMode(AccessMode::WRITE())),
            $tsxHandler
        );
    }

    public function readTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        $config = $this->mergeTsxConfig($config);

        return TransactionHelper::retry(
            fn () => $this->startTransaction($config, $this->config->withAccessMode(AccessMode::READ())),
            $tsxHandler
        );
    }

    public function transaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return $this->writeTransaction($tsxHandler, $config);
    }

    public function beginTransaction(?iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        $config = $this->mergeTsxConfig($config);
        $tsx = $this->startTransaction($config, $this->config);

        $tsx->runStatements($statements ?? []);

        return $tsx;
    }

    /**
     * @return UnmanagedTransactionInterface<ResultFormat>
     */
    private function beginInstantTransaction(
        SessionConfiguration $config,
        TransactionConfiguration $tsxConfig
    ): TransactionInterface {
        $connection = $this->acquireConnection($tsxConfig, $config);

        return new BoltUnmanagedTransaction($this->config->getDatabase(), $this->formatter, $connection, $this->config, $tsxConfig);
    }

    /**
     * @throws Exception
     */
    private function acquireConnection(TransactionConfiguration $config, SessionConfiguration $sessionConfig): BoltConnection
    {
        $connection = $this->pool->acquire($this->uri, $this->auth, $sessionConfig);
        $connection->setTimeout($config->getTimeout());

        return $connection;
    }

    private function startTransaction(TransactionConfiguration $config, SessionConfiguration $sessionConfig): UnmanagedTransactionInterface
    {
        try {
            $connection = $this->acquireConnection($config, $sessionConfig);

            $connection->getImplementation()->begin(['db' => $this->config->getDatabase(), 'tx_timeout' => (int) ($config->getTimeout() * 1000)]);
        } catch (MessageException $e) {
            if (isset($connection)) {
                $connection->reset();
            }
            throw Neo4jException::fromMessageException($e);
        }

        return new BoltUnmanagedTransaction($this->config->getDatabase(), $this->formatter, $connection, $this->config, $config);
    }

    private function mergeTsxConfig(?TransactionConfiguration $config): TransactionConfiguration
    {
        return TransactionConfiguration::default()->merge($config);
    }
}
