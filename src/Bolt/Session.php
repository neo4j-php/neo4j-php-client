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
            // Wrap in retry logic for connection errors
            $retries = 0;
            $maxRetries = 3;

            while ($retries < $maxRetries) {
                try {
                    $tbr[] = $this->beginInstantTransaction($this->config, $config)->runStatement($statement);
                    break; // Success, exit retry loop
                } catch (Neo4jException $e) {
                    if ($this->shouldClearRoutingTable($e)) {
                        $this->getLogger()?->log(LogLevel::WARNING, 'Connection error in instant transaction, retrying', [
                            'error' => $e->getMessage(),
                            'retry' => $retries + 1,
                        ]);

                        if ($this->pool instanceof Neo4jConnectionPool) {
                            $this->pool->clearRoutingTable($this->config);
                        }
                        $this->pool->close();

                        ++$retries;
                        if ($retries >= $maxRetries) {
                            throw $e;
                        }
                    } else {
                        throw $e;
                    }
                } catch (Throwable $e) {
                    if ($this->isConnectionError($e)) {
                        $this->getLogger()?->log(LogLevel::WARNING, 'Connection error in instant transaction, retrying', [
                            'error' => $e->getMessage(),
                            'retry' => $retries + 1,
                        ]);

                        if ($this->pool instanceof Neo4jConnectionPool) {
                            $this->pool->clearRoutingTable($this->config);
                        }
                        $this->pool->close();

                        ++$retries;
                        if ($retries >= $maxRetries) {
                            throw $e;
                        }
                    } else {
                        throw $e;
                    }
                }
            }
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
                if ($transaction && !in_array($e->getClassification(), self::ROLLBACK_CLASSIFICATIONS)) {
                    $transaction->rollback();
                }

                // ADD THIS SECTION - Handle connection timeouts and routing failures
                if ($e->getTitle() === 'NotALeader'
                    || $e->getNeo4jCode() === 'Neo.ClientError.Cluster.NotALeader'
                    || $this->isConnectionError($e)) {
                    // Clear routing table before closing pool to force fresh ROUTE request on retry
                    if ($this->pool instanceof Neo4jConnectionPool) {
                        $this->pool->clearRoutingTable($this->config);
                    }
                    // By closing the pool, we force the connection to be re-acquired and the routing table to be refetched
                    $this->pool->close();
                } elseif ($e->getClassification() !== 'TransientError') {
                    throw $e;
                }
            } catch (Exception $e) {
                if ($this->isConnectionError($e)) {
                    if ($this->pool instanceof Neo4jConnectionPool) {
                        $this->pool->clearRoutingTable($this->config);
                    }
                    $this->pool->close();
                } else {
                    throw $e;
                }
            }
        }
    }

    /**
     * Check if the exception is a connection-related error.
     */
    private function isConnectionError(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        // Check for common connection error messages
        if (str_contains($message, 'interrupted system call')
            || str_contains($message, 'broken pipe')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection timeout')
            || str_contains($message, 'connection closed')) {
            return true;
        }

        // Check for Neo4jException-specific codes
        if ($e instanceof Neo4jException) {
            return $e->getNeo4jCode() === 'Neo.ClientError.Cluster.NotALeader';
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

        // Clear routing table for timeout, connection, and cluster errors
        return str_contains($message, 'interrupted system call')
            || str_contains($message, 'broken pipe')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection timeout')
            || str_contains($message, 'connection closed')
            || $e->getNeo4jCode() === 'Neo.ClientError.Cluster.NotALeader'
            || $title === 'NotALeader';
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

        return new BoltUnmanagedTransaction(
            $this->config->getDatabase(),
            $this->formatter,
            $connection,
            $this->config,
            $tsxConfig,
            $this->bookmarkHolder,
            new BoltMessageFactory($connection, $this->getLogger()),
            true,
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
        try {
            $connection = $this->acquireConnection($config, $sessionConfig);

            $connection->begin($this->config->getDatabase(), $config->getTimeout(), $this->bookmarkHolder, $config->getMetaData());
        } catch (Neo4jException $e) {
            if (isset($connection) && $connection->getServerState() === 'FAILED') {
                $connection->reset();
            }
            throw $e;
        }

        return new BoltUnmanagedTransaction(
            $this->config->getDatabase(),
            $this->formatter,
            $connection,
            $this->config,
            $config,
            $this->bookmarkHolder,
            new BoltMessageFactory($connection, $this->getLogger()),
            false
        );
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
