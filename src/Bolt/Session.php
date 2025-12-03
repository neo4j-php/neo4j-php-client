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
use Laudis\Neo4j\Exception\TimeoutException;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Neo4j\Neo4jConnectionPool;
use Laudis\Neo4j\Types\CypherList;
use Psr\Log\LogLevel;

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
            $tbr[] = $this->runStatementWithRetry($statement, $config);
        }

        return new CypherList($tbr);
    }

    /**
     * Runs a single statement with retry logic to handle socket timeouts and routing errors.
     * For routing drivers, this is essential to refresh the routing table on timeout.
     *
     * @throws Exception
     */
    private function runStatementWithRetry(Statement $statement, TransactionConfiguration $config): SummarizedResult
    {
        while (true) {
            $transaction = null;
            try {
                $transaction = $this->beginInstantTransaction($this->config, $config);
                $result = $transaction->runStatement($statement);
                // Trigger lazy loading of results to catch any timeout during result iteration
                self::triggerLazyResult($result);

                return $result;
            } catch (TimeoutException $e) {
                // Socket timeout - clear routing table and retry
                if ($transaction) {
                    try {
                        $transaction->rollback();
                    } catch (Exception $rollbackException) {
                        // Ignore rollback errors during timeout
                    }
                }

                // Close broken connection so it won't be reused
                foreach ($this->usedConnections as $i => $usedConnection) {
                    try {
                        $usedConnection->close();
                        array_splice($this->usedConnections, $i, 1);
                    } catch (Exception $closeException) {
                        // Ignore close errors
                    }
                }

                if ($this->pool instanceof Neo4jConnectionPool) {
                    $this->pool->clearRoutingTable();
                }
                // Continue retry loop
            } catch (Neo4jException $e) {
                if ($transaction && !in_array($e->getClassification(), self::ROLLBACK_CLASSIFICATIONS)) {
                    try {
                        $transaction->rollback();
                    } catch (Exception $rollbackException) {
                        // Ignore rollback errors
                    }
                }

                if ($this->isSocketTimeoutError($e)) {
                    // When socket timeout occurs, clear routing table to force re-fetch with fresh server list
                    if ($this->pool instanceof Neo4jConnectionPool) {
                        $this->pool->clearRoutingTable();
                    }
                    // Continue retry loop
                } elseif ($e->getTitle() === 'NotALeader') {
                    // By closing the pool, we force the connection to be re-acquired and the routing table to be refetched
                    $this->pool->close();
                    // Continue retry loop
                } elseif ($e->getClassification() !== 'TransientError') {
                    throw $e;
                }
                // For other transient errors, continue retry loop
            }
        }
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
            } catch (TimeoutException $e) {
                // Socket timeout - clear routing table and retry
                if ($transaction) {
                    try {
                        $transaction->rollback();
                    } catch (Exception $rollbackException) {
                        // Ignore rollback errors during timeout
                    }
                }

                // Close broken connection so it won't be reused
                foreach ($this->usedConnections as $i => $usedConnection) {
                    try {
                        $usedConnection->close();
                        array_splice($this->usedConnections, $i, 1);
                    } catch (Exception $closeException) {
                        // Ignore close errors
                    }
                }

                if ($this->pool instanceof Neo4jConnectionPool) {
                    $this->pool->clearRoutingTable();
                }
                // Continue retry loop
            } catch (Neo4jException $e) {
                if ($transaction && !in_array($e->getClassification(), self::ROLLBACK_CLASSIFICATIONS)) {
                    $transaction->rollback();
                }

                if ($this->isSocketTimeoutError($e)) {
                    // When socket timeout occurs, clear routing table to force re-fetch with fresh server list
                    if ($this->pool instanceof Neo4jConnectionPool) {
                        $this->pool->clearRoutingTable();
                    }
                } elseif ($e->getTitle() === 'NotALeader') {
                    // By closing the pool, we force the connection to be re-acquired and the routing table to be refetched
                    $this->pool->close();
                } elseif ($e->getClassification() !== 'TransientError') {
                    throw $e;
                }
            }
        }
    }

    /**
     * Checks if an exception represents a socket timeout or connection-related failure
     * that requires routing table refresh.
     *
     * @param Neo4jException $e The exception to check
     *
     * @return bool True if this is a socket timeout or connection failure
     */
    private function isSocketTimeoutError(Neo4jException $e): bool
    {
        $title = $e->getTitle();
        $classification = $e->getClassification();

        // Check if this was caused by a timeout exception in the bolt library
        // Timeout exceptions are wrapped in Neo4jException with NotALeader title,
        // but we can detect them by checking the previous exception message
        $previous = $e->getPrevious();
        if ($previous !== null) {
            $prevMessage = strtolower($previous->getMessage());
            if (str_contains($prevMessage, 'timeout') || str_contains($prevMessage, 'time out')) {
                return true;
            }
        }

        // Socket timeout errors should be treated as transient and trigger routing table refresh
        return in_array($title, [
            'ServiceUnavailable',
            'FailedToRoute',
        ], true) || $classification === 'TransientError';
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
