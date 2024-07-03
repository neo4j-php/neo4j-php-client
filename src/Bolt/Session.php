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
use Laudis\Neo4j\Common\TransactionHelper;
use Laudis\Neo4j\Contracts\SessionInterface;
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
use Laudis\Neo4j\Types\ArrayList;

/**
 * A session using bolt connections.
 */
final class Session
{
    /** @psalm-readonly */
    private readonly BookmarkHolder $bookmarkHolder;

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        /** @psalm-readonly */
        private readonly SessionConfiguration $config,
        /** @psalm-readonly */
        private readonly ConnectionPool|Neo4jConnectionPool $pool,
        /** @psalm-readonly */
        private readonly SummarizedResultFormatter $formatter
    ) {
        $this->bookmarkHolder = new BookmarkHolder(Bookmark::from($config->getBookmarks()));
    }

    /**
     * @return ArrayList<SummarizedResult>
     */
    public function runStatements(iterable $statements, ?TransactionConfiguration $config = null): ArrayList
    {
        $tbr = [];

        $config = $this->mergeTsxConfig($config);
        foreach ($statements as $statement) {
            $tbr[] = $this->beginInstantTransaction($this->config, $config)->runStatement($statement);
        }

        return new ArrayList($tbr);
    }

    public function runStatement(Statement $statement, ?TransactionConfiguration $config = null): SummarizedResult
    {
        return $this->runStatements([$statement], $config)->first();
    }

    public function run(string $statement, iterable $parameters = [], ?TransactionConfiguration $config = null): SummarizedResult
    {
        return $this->runStatement(new Statement($statement, $parameters), $config);
    }

    public function writeTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null): mixed
    {
        $config = $this->mergeTsxConfig($config);

        return TransactionHelper::retry(
            fn () => $this->startTransaction($config, $this->config->withAccessMode(AccessMode::WRITE())),
            $tsxHandler
        );
    }

    public function readTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null): mixed
    {
        $config = $this->mergeTsxConfig($config);

        return TransactionHelper::retry(
            fn () => $this->startTransaction($config, $this->config->withAccessMode(AccessMode::READ())),
            $tsxHandler
        );
    }

    public function transaction(callable $tsxHandler, ?TransactionConfiguration $config = null): mixed
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

    private function beginInstantTransaction(
        SessionConfiguration $config,
        TransactionConfiguration $tsxConfig
    ): BoltUnmanagedTransaction {
        $connection = $this->acquireConnection($tsxConfig, $config);

        return new BoltUnmanagedTransaction($this->config->getDatabase(), $this->formatter, $connection, $this->config, $tsxConfig, $this->bookmarkHolder);
    }

    /**
     * @throws Exception
     */
    private function acquireConnection(TransactionConfiguration $config, SessionConfiguration $sessionConfig): BoltConnection
    {
        $connection = $this->pool->acquire($sessionConfig);
        $connection = GeneratorHelper::getReturnFromGenerator($connection);

        // We try and let the server do the timeout management.
        // Since the client should not run indefinitely, we just add the client side by two, just in case
        $timeout = $config->getTimeout();
        if ($timeout !== null) {
            $timeout = ($timeout < 30) ? 30 : $timeout;
            $connection->setTimeout($timeout + 2);
        }

        return $connection;
    }

    private function startTransaction(TransactionConfiguration $config, SessionConfiguration $sessionConfig): UnmanagedTransactionInterface
    {
        try {
            $connection = $this->acquireConnection($config, $sessionConfig);

            $connection->begin($this->config->getDatabase(), $config->getTimeout(), $this->bookmarkHolder);
        } catch (Neo4jException $e) {
            if (isset($connection) && $connection->getServerState() === 'FAILED') {
                $connection->reset();
            }
            throw $e;
        }

        return new BoltUnmanagedTransaction($this->config->getDatabase(), $this->formatter, $connection, $this->config, $config, $this->bookmarkHolder);
    }

    private function mergeTsxConfig(?TransactionConfiguration $config): TransactionConfiguration
    {
        return TransactionConfiguration::default()->merge($config);
    }

    public function getLastBookmark(): Bookmark
    {
        return $this->bookmarkHolder->getBookmark();
    }
}
