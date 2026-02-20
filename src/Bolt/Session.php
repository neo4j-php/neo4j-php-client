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

namespace Laudis\Neo4j\Bolt;

use Bolt\error\ConnectException as BoltConnectException;
use Exception;
use Laudis\Neo4j\Common\GeneratorHelper;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Contracts\CypherSequence;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Neo4j\Neo4jConnectionPool;
use Laudis\Neo4j\Types\CypherList;
use Psr\Log\LogLevel;
use Throwable;

/**
 * A session using bolt connections.
 */
final class Session implements SessionInterface
{
    private const ROLLBACK_CLASSIFICATIONS = ['ClientError', 'TransientError', 'DatabaseError'];

    /** @var list<BoltConnection> */
    private array $usedConnections = [];
    /** @psalm-readonly */
    private readonly BookmarkHolder $bookmarkHolder;

    /**
     * @param ConnectionPool|Neo4jConnectionPool $pool
     *
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly SessionConfiguration $config,
        private readonly ConnectionPoolInterface $pool,
        /**
         * @psalm-readonly
         */
        private readonly SummarizedResultFormatter $formatter,
    ) {
        $this->bookmarkHolder = new BookmarkHolder(Bookmark::from($config->getBookmarks()));
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @return CypherList<SummarizedResult>
     */
    public function runStatements(iterable $statements, ?TransactionConfiguration $config = null): CypherList
    {
        $tbr = [];

        $this->getLogger()?->log(LogLevel::INFO, 'Running statements', ['statements' => $statements]);
        $config = $this->mergeTsxConfig($config);

        foreach ($statements as $statement) {
            $tbr[] = $this->executeStatementWithRetry($statement, $config);
        }

        return new CypherList($tbr);
    }

    /**
     * @param iterable<Statement>|null $statements
     */
    public function openTransaction(?iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        return $this->beginTransaction($statements, $this->mergeTsxConfig($config));
    }

    public function runStatement(Statement $statement, ?TransactionConfiguration $config = null): SummarizedResult
    {
        return $this->runStatements([$statement], $config)->first();
    }

    public function run(string $statement, iterable $parameters = [], ?TransactionConfiguration $config = null): SummarizedResult
    {
        return $this->runStatement(new Statement($statement, $parameters), $config);
    }

    public function writeTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        $this->getLogger()?->log(LogLevel::INFO, 'Beginning write transaction', ['config' => $config]);
        $config = $this->mergeTsxConfig($config);

        return $this->retry($tsxHandler, false, $config);
    }

    public function readTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        $this->getLogger()?->log(LogLevel::INFO, 'Beginning read transaction', ['config' => $config]);
        $config = $this->mergeTsxConfig($config);

        return $this->retry($tsxHandler, true, $config);
    }

    /**
     * @template U
     *
     * @param callable(TransactionInterface):U $tsxHandler
     *
     * @return U
     */
    private function retry(callable $tsxHandler, bool $read, TransactionConfiguration $config)
    {
        while (true) {
            $transaction = null;
            try {
                if ($read) {
                    $transaction = $this->startTransaction($config, $this->config->withAccessMode(AccessMode::READ()));
                } else {
                    $transaction = $this->startTransaction($config, $this->config->withAccessMode(AccessMode::WRITE()));
                }
                $tbr = $tsxHandler($transaction);
                self::triggerLazyResult($tbr);
                $transaction->commit();

                return $tbr;
            } catch (Neo4jException $e) {
                $this->handleManagedTransactionError($transaction, $e);
            } catch (Throwable $e) {
                // For non-Neo4jException errors, only retry on connection errors
                if (!$this->isConnectionError($e)) {
                    throw $e;
                }
                // Connection error - clear routing and retry
                $this->handleConnectionFailure();
            }
        }
    }

    /**
     * Handle Neo4jException in managed transaction - either rollback and retry or throw.
     *
     * @param Neo4jException $e The exception that occurred
     */
    private function handleManagedTransactionError(?UnmanagedTransactionInterface $transaction, Neo4jException $e): void
    {
        if ($transaction && !in_array($e->getClassification(), self::ROLLBACK_CLASSIFICATIONS)) {
            $transaction->rollback();
        }

        if ($this->shouldRetryManagedTransaction($e)) {
            $this->handleConnectionFailure();

            return;
        }

        throw $e;
    }

    /**
     * Determine if a Neo4jException should trigger retry of managed transaction.
     */
    private function shouldRetryManagedTransaction(Neo4jException $e): bool
    {
        if ($e->getTitle() === 'NotALeader' || $e->getNeo4jCode() === 'Neo.ClientError.Cluster.NotALeader') {
            return true;
        }

        if ($this->isConnectionError($e)) {
            return true;
        }

        return $e->getClassification() === 'TransientError';
    }

    /**
     * Handle connection failure by clearing routing table and closing pool.
     * This forces fresh connection acquisition and routing table refresh on next attempt.
     */
    private function handleConnectionFailure(): void
    {
        if ($this->pool instanceof Neo4jConnectionPool) {
            $this->pool->clearRoutingTable($this->config);
        }
        $this->pool->close();
    }

    /**
     * Check if the exception is a connection-related error.
     */
    private function isConnectionError(Throwable $e): bool
    {
        if ($e instanceof BoltConnectException) {
            return true;
        }

        $message = strtolower($e->getMessage());

        if (str_contains($message, 'interrupted system call')
            || str_contains($message, 'broken pipe')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'connection timeout')
            || str_contains($message, 'connection closed')
            || str_contains($message, 'i/o error')
            || str_contains($message, 'timeout')
            || str_contains($message, 'time out')) {
            return true;
        }

        return false;
    }

    /**
     * Check if the exception should trigger a routing table clear.
     */
    private function shouldClearRoutingTable(Neo4jException $e): bool
    {
        $message = strtolower($e->getMessage());
        $title = $e->getTitle();

        return str_contains($message, 'interrupted system call')
            || str_contains($message, 'broken pipe')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection timeout')
            || str_contains($message, 'connection closed')
            || $e->getNeo4jCode() === 'Neo.ClientError.Cluster.NotALeader'
            || $title === 'NotALeader';
    }

    /**
     * Execute a statement with automatic retry on connection errors.
     * Retries up to 3 times on connection failures, clearing routing table between attempts.
     *
     * @param Statement                $statement The statement to execute
     * @param TransactionConfiguration $config    Transaction configuration
     *
     * @return SummarizedResult The result of the statement
     */

    /**
     * Execute instant transaction (session.run) with automatic retry on connection/routing errors.
     *
     * PURPOSE:
     * - Handles transient failures transparently to user: connection timeouts, server unavailable, etc.
     * - Supports cluster failover: when server goes down, clears routing table and retries on different node
     * - Distinguishes errors: retries on connection/routing issues but fails immediately on client errors (syntax, auth)
     * - Improves reliability: 3 retry attempts with fresh routing table each time = high availability
     *
     * EXAMPLE: User calls session.run("CREATE (n)") during cluster failover:
     *   Attempt 1: Node A is leader → "NotALeader" (stepping down) → Clear routing table
     *   Attempt 2: Node B elected leader → "Connection timeout" (election in progress) → Retry
     *   Attempt 3: Cluster stable → Query succeeds
     *   User sees: Query succeeded transparently (no exception, no manual retry needed)
     *
     * WHY THIS IS CRITICAL FOR DRIVERS:
     * - All Neo4j drivers (Java, Python, JavaScript) have this pattern
     * - Without it: user must manually retry or wrap every session.run() in try-catch
     * - With it: driver handles recovery automatically = better UX and reliability
     */
    private function executeStatementWithRetry(Statement $statement, TransactionConfiguration $config): SummarizedResult
    {    // Retry instant transactions up to 3 times on connection/routing errors; catch distinguishes retryable errors from client errors (syntax, auth) and clears routing table for cluster failover.
        $maxRetries = 3;
        $retries = 0;

        while ($retries < $maxRetries) {
            try {
                return $this->beginInstantTransaction($this->config, $config)->runStatement($statement);
            } catch (Neo4jException $e) {
                if (!$this->shouldClearRoutingTable($e)) {
                    throw $e;
                }
                $this->handleStatementRetry($retries, $maxRetries, $e);
            } catch (Throwable $e) {
                if (!$this->isConnectionError($e)) {
                    throw $e;
                }
                $this->handleStatementRetry($retries, $maxRetries, $e);
            }
        }

        throw new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.General', 'Statement execution failed after maximum retries')]);
    }

    /**
     * Handle retry logic for statement execution - clear routing and increment counter.
     * Throws the exception if max retries exceeded.
     */
    private function handleStatementRetry(int &$retries, int $maxRetries, Throwable $e): void
    {
        $this->getLogger()?->log(LogLevel::WARNING, 'Connection error in instant transaction, retrying', [
            'error' => $e->getMessage(),
            'retry' => $retries + 1,
        ]);

        $this->handleConnectionFailure();

        ++$retries;
        if ($retries >= $maxRetries) {
            throw $e;
        }
    }

    private static function triggerLazyResult(mixed $tbr): void
    {
        if ($tbr instanceof CypherSequence) {
            $tbr->preload();
        }
    }

    public function transaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return $this->writeTransaction($tsxHandler, $config);
    }

    /**
     * @param iterable<Statement> $statements
     */
    public function beginTransaction(?iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        $this->getLogger()?->log(LogLevel::INFO, 'Beginning transaction', ['statements' => $statements, 'config' => $config]);
        $config = $this->mergeTsxConfig($config);
        $tsx = $this->startTransaction($config, $this->config);

        $tsx->runStatements($statements ?? []);

        return $tsx;
    }

    /**
     * @return UnmanagedTransactionInterface
     */
    private function beginInstantTransaction(
        SessionConfiguration $config,
        TransactionConfiguration $tsxConfig,
    ): TransactionInterface {
        $this->getLogger()?->log(LogLevel::INFO, 'Starting instant transaction', ['config' => $tsxConfig]);
        $connection = $this->acquireConnection($tsxConfig, $config);

        /** @var ConnectionPoolInterface|null $pool */
        $pool = $this->pool;

        return new BoltUnmanagedTransaction(
            $this->config->getDatabase(),
            $this->formatter,
            $connection,
            $this->config,
            $tsxConfig,
            $this->bookmarkHolder,
            new BoltMessageFactory($connection, $this->getLogger()),
            true,
            $pool,
        );
    }

    /**
     * @throws Exception
     */
    private function acquireConnection(TransactionConfiguration $config, SessionConfiguration $sessionConfig): BoltConnection
    {
        $this->getLogger()?->log(LogLevel::INFO, 'Acquiring connection', ['config' => $config, 'sessionConfig' => $sessionConfig]);
        $connectionGenerator = $this->pool->acquire($sessionConfig);
        /**
         * @var BoltConnection $connection
         *
         * @psalm-suppress UnnecessaryVarAnnotation
         */
        $connection = GeneratorHelper::getReturnFromGenerator($connectionGenerator);

        // We try and let the server do the timeout management.
        // Since the client should not run indefinitely, we just add the client side by two, just in case
        $timeout = $config->getTimeout();
        if ($timeout !== null) {
            $timeout = ($timeout < 30) ? 30 : $timeout;
            $connection->setTimeout($timeout + 2);
        }
        $this->usedConnections[] = $connection;

        return $connection;
    }

    private function startTransaction(TransactionConfiguration $config, SessionConfiguration $sessionConfig): UnmanagedTransactionInterface
    {
        $this->getLogger()?->log(LogLevel::INFO, 'Starting transaction', ['config' => $config, 'sessionConfig' => $sessionConfig]);
        $connection = $this->acquireConnection($config, $sessionConfig);

        try {
            $connection->begin($this->config->getDatabase(), $config->getTimeout(), $this->bookmarkHolder, $config->getMetaData());
        } catch (Neo4jException $e) {
            // BEGIN failed - clean up connection before rethrowing
            $this->cleanupFailedConnection($connection);
            throw $e;
        }

        /** @var ConnectionPoolInterface|null $pool */
        $pool = $this->pool;

        return new BoltUnmanagedTransaction(
            $this->config->getDatabase(),
            $this->formatter,
            $connection,
            $this->config,
            $config,
            $this->bookmarkHolder,
            new BoltMessageFactory($connection, $this->getLogger()),
            false,
            $pool,
        );
    }

    /**
     * Clean up a connection that failed during BEGIN or other initialization.
     * Resets the connection if it's in FAILED state and releases it back to the pool.
     */
    private function cleanupFailedConnection(BoltConnection $connection): void
    {
        if ($connection->getServerState() === 'FAILED') {
            $connection->reset();
        }
        // Release connection back to pool for reuse
        $this->pool->release($connection);
    }

    private function mergeTsxConfig(?TransactionConfiguration $config): TransactionConfiguration
    {
        return TransactionConfiguration::default()->merge($config);
    }

    public function getLastBookmark(): Bookmark
    {
        return $this->bookmarkHolder->getBookmark();
    }

    public function close(): void
    {
        foreach ($this->usedConnections as $connection) {
            $connection->discardUnconsumedResults();
        }
        $this->usedConnections = [];
    }

    private function getLogger(): ?Neo4jLogger
    {
        return $this->pool->getLogger();
    }
}
