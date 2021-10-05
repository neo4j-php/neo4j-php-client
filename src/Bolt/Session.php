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

use Bolt\Bolt;
use Exception;
use Laudis\Neo4j\Common\TransactionHelper;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Exception\Neo4jException;
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
     * @var ConnectionPoolInterface<Bolt>
     */
    private ConnectionPoolInterface $pool;
    /**
     * @psalm-readonly
     *
     * @var FormatterInterface<ResultFormat>
     */
    private FormatterInterface $formatter;
    /** @psalm-readonly  */
    private string $userAgent;
    private UriInterface $uri;
    /** @psalm-readonly  */
    private AuthenticateInterface $auth;
    /** @psalm-readonly  */
    private float $socketTimeout;

    /**
     * @param FormatterInterface<ResultFormat> $formatter
     * @param ConnectionPoolInterface<Bolt>    $pool
     *
     * @psalm-mutation-free
     */
    public function __construct(
        SessionConfiguration $config,
        ConnectionPoolInterface $pool,
        FormatterInterface $formatter,
        string $userAgent,
        UriInterface $uri,
        AuthenticateInterface $auth,
        float $socketTimeout
    ) {
        $this->config = $config;
        $this->pool = $pool;
        $this->formatter = $formatter;
        $this->userAgent = $userAgent;
        $this->uri = $uri;
        $this->auth = $auth;
        $this->socketTimeout = $socketTimeout;
    }

    public function runStatements(iterable $statements, ?TransactionConfiguration $config = null): CypherList
    {
        return $this->beginInstantTransaction($this->config)->runStatements($statements);
    }

    public function openTransaction(iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        return $this->beginTransaction($statements, $config);
    }

    public function runStatement(Statement $statement, ?TransactionConfiguration $config = null)
    {
        return $this->runStatements([$statement])->first();
    }

    public function run(string $statement, iterable $parameters = [], ?TransactionConfiguration $config = null)
    {
        return $this->runStatement(new Statement($statement, $parameters));
    }

    public function writeTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        $config ??= TransactionConfiguration::default();

        return TransactionHelper::retry(
            fn () => $this->startTransaction($config, $this->config->withAccessMode(AccessMode::WRITE())),
            $tsxHandler,
            $config
        );
    }

    public function readTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        $config ??= TransactionConfiguration::default();

        return TransactionHelper::retry(
            fn () => $this->startTransaction($config, $this->config->withAccessMode(AccessMode::READ())),
            $tsxHandler,
            $config
        );
    }

    public function transaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return $this->writeTransaction($tsxHandler, $config);
    }

    public function beginTransaction(?iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        $config ??= TransactionConfiguration::default();
        $tsx = $this->startTransaction($config, $this->config);

        $tsx->runStatements($statements ?? []);

        return $tsx;
    }

    /**
     * @return UnmanagedTransactionInterface<ResultFormat>
     */
    private function beginInstantTransaction(SessionConfiguration $config): TransactionInterface
    {
        $connection = $this->acquireConnection(TransactionConfiguration::default(), $config);

        return new BoltUnmanagedTransaction($this->config->getDatabase(), $this->formatter, $connection);
    }

    /**
     * @throws Exception
     *
     * @return ConnectionInterface<Bolt>
     */
    private function acquireConnection(TransactionConfiguration $config, SessionConfiguration $sessionConfig): ConnectionInterface
    {
        $timeout = max($this->socketTimeout, $config->getTimeout());

        return $this->pool->acquire($this->uri, $this->auth, $timeout, $this->userAgent, $sessionConfig);
    }

    private function startTransaction(TransactionConfiguration $config, SessionConfiguration $sessionConfig): UnmanagedTransactionInterface
    {
        try {
            $bolt = $this->acquireConnection($config, $sessionConfig);

            $begin = $bolt->getImplementation()->begin(['db' => $this->config->getDatabase()]);

            if (!$begin) {
                throw new Neo4jException([new Neo4jError('', 'Cannot open new transaction')]);
            }
        } catch (Exception $e) {
            if ($e instanceof Neo4jException) {
                throw $e;
            }
            $code = TransactionHelper::extractCode($e) ?? '';
            throw new Neo4jException([new Neo4jError($code, $e->getMessage())], $e);
        }

        return new BoltUnmanagedTransaction($this->config->getDatabase(), $this->formatter, $bolt);
    }
}
