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

use Bolt\enum\ServerState;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\TransactionState;
use Laudis\Neo4j\Exception\TransactionException;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\ParameterHelper;
use Laudis\Neo4j\Types\CypherList;

use function microtime;

use Throwable;

/**
 * Manages a transaction over the bolt protocol.
 *
 * @psalm-import-type BoltMeta from SummarizedResultFormatter
 */
final class BoltUnmanagedTransaction implements UnmanagedTransactionInterface
{
    private TransactionState $state = TransactionState::ACTIVE;
    private bool $beginSent = false;

    public function __construct(
        /** @psalm-readonly */
        private readonly ?string $database,
        /**
         * @psalm-readonly
         */
        private readonly SummarizedResultFormatter $formatter,
        /** @psalm-readonly */
        private readonly BoltConnection $connection,
        private readonly SessionConfiguration $config,
        private readonly TransactionConfiguration $tsxConfig,
        private readonly BookmarkHolder $bookmarkHolder,
        private readonly BoltMessageFactory $messageFactory,
        private readonly bool $isInstantTransaction,
        private readonly ?ConnectionPoolInterface $pool = null,
        bool $beginAlreadySent = false,
    ) {
        $this->beginSent = $beginAlreadySent;
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @throws TransactionException|Throwable
     *
     * @return CypherList<SummarizedResult>
     */
    public function commit(iterable $statements = []): CypherList
    {
        if ($this->isFinished()) {
            if ($this->state === TransactionState::TERMINATED) {
                throw new TransactionException("Can't commit a terminated transaction.");
            }

            if ($this->state === TransactionState::COMMITTED) {
                throw new TransactionException("Can't commit a committed transaction.");
            }

            if ($this->state === TransactionState::ROLLED_BACK) {
                throw new TransactionException("Can't commit a committed transaction.");
            }
        }

        $this->ensureBeginSent();

        $this->connection->consumeResults();

        // Force the results to pull all the results.
        // After a commit, the connection will be in the ready state, making it impossible to use PULL
        $tbr = $this->runStatements($statements)->each(static function (CypherList $list) {
            $list->preload();
        });

        $this->messageFactory->createCommitMessage($this->bookmarkHolder)->send()->getResponse();
        $this->state = TransactionState::COMMITTED;

        return $tbr;
    }

    public function rollback(): void
    {
        if ($this->isFinished()) {
            if ($this->state === TransactionState::TERMINATED) {
                // Run/pull already failed; connection may have been RESET — nothing to send.
                return;
            }

            if ($this->state === TransactionState::COMMITTED) {
                throw new TransactionException("Can't rollback a committed transaction.");
            }

            if ($this->state === TransactionState::ROLLED_BACK) {
                throw new TransactionException("Can't rollback a rolled back transaction.");
            }
        }

        $this->ensureBeginSent();

        // FAILURE on PULL triggers RESET in {@see BoltConnection::assertNoFailure()}; server has no open tx.
        // TestKit stubs (e.g. tx_error_on_pull.script) expect no ROLLBACK after that RESET.
        if ($this->connection->getServerState() === 'READY') {
            $this->state = TransactionState::ROLLED_BACK;

            return;
        }

        $this->messageFactory->createRollbackMessage()->send();
        $this->state = TransactionState::ROLLED_BACK;
    }

    /**
     * @throws Throwable
     */
    public function run(string $statement, iterable $parameters = []): SummarizedResult
    {
        if ($this->isFinished()) {
            if ($this->state === TransactionState::TERMINATED) {
                throw new TransactionException("Can't run a query on a terminated transaction.");
            }

            if ($this->state === TransactionState::COMMITTED) {
                throw new TransactionException("Can't run a query on a committed transaction.");
            }

            if ($this->state === TransactionState::ROLLED_BACK) {
                throw new TransactionException("Can't run a query on a rolled back transaction.");
            }
        }

        return $this->runStatement(new Statement($statement, $parameters));
    }

    /**
     * @throws Throwable
     */
    public function runStatement(Statement $statement): SummarizedResult
    {
        $parameters = ParameterHelper::formatParameters($statement->getParameters(), $this->connection->getProtocol());
        $start = microtime(true);

        // Only drain an outstanding autocommit result (STREAMING). In an explicit transaction (TX_STREAMING)
        // several RUN streams may be open; consumeResults() would preload other streams and reorder PULLs
        // vs RUN (TestKit tx_pull_1_nested*, Neo4j parallel/nested tx tests).
        if ($this->connection->getServerState() === ServerState::STREAMING->name) {
            $this->connection->consumeResults();
        }

        $this->ensureBeginSent();

        try {
            $meta = $this->connection->run(
                $statement->getText(),
                $parameters->toArray(),
                $this->database,
                $this->tsxConfig->getTimeout(),
                $this->isInstantTransaction ? $this->bookmarkHolder : null, // let the begin transaction pass the bookmarks if it is a managed transaction
                null, // mode is never sent in RUN messages - it comes from session configuration
                $this->tsxConfig->getMetaData()
            );
        } catch (Throwable $e) {
            $this->state = TransactionState::TERMINATED;
            if ($this->pool !== null) {
                $this->pool->release($this->connection);
            }
            throw $e;
        }
        $run = microtime(true);

        return $this->formatter->formatBoltResult(
            $meta,
            new BoltResult($this->connection, $this->config->getFetchSize(), $meta['qid'] ?? -1),
            $this->connection,
            $start,
            $run - $start,
            $statement,
            $this->bookmarkHolder
        );
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @throws Throwable
     *
     * @return CypherList<SummarizedResult>
     */
    public function runStatements(iterable $statements): CypherList
    {
        $tbr = [];
        foreach ($statements as $statement) {
            $tbr[] = $this->runStatement($statement);
        }

        return new CypherList($tbr);
    }

    public function isRolledBack(): bool
    {
        return $this->state === TransactionState::ROLLED_BACK || $this->state === TransactionState::TERMINATED;
    }

    public function isCommitted(): bool
    {
        return $this->state == TransactionState::COMMITTED;
    }

    public function isFinished(): bool
    {
        return $this->state != TransactionState::ACTIVE;
    }

    private function ensureBeginSent(): void
    {
        if ($this->isInstantTransaction) {
            return;
        }
        // FAILURE on PULL triggers RESET in BoltConnection — server is READY with no tx, but we may still
        // have beginSent=true (e.g. execute_read retry). Must send BEGIN again before RUN.
        if ($this->beginSent && $this->state === TransactionState::ACTIVE && $this->connection->getServerState() === 'READY') {
            $this->beginSent = false;
        }
        if ($this->beginSent) {
            return;
        }
        try {
            $this->connection->begin($this->database, $this->tsxConfig->getTimeout(), $this->bookmarkHolder, $this->tsxConfig->getMetaData());
            $this->beginSent = true;
        } catch (Throwable $e) {
            $this->state = TransactionState::TERMINATED;
            if ($this->pool !== null) {
                $this->pool->release($this->connection);
            }
            throw $e;
        }
    }
}
