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
use Laudis\Neo4j\Exception\Neo4jException;
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
        private readonly TelemetryAPIEnum $telemetryApi,
        private readonly ?ConnectionPoolInterface $pool = null,
        bool $beginAlreadySent = false,
        private readonly ?SessionBookmarkTracker $bookmarkTracker = null,
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

        // Force the results to pull all the results.
        // After a commit, the connection will be in the ready state, making it impossible to use PULL
        $tbr = $this->runStatements($statements)->each(static function (CypherList $list) {
            $list->preload();
        });

        $this->connection->consumeResults();

        try {
            $response = $this->messageFactory->createCommitMessage($this->bookmarkHolder)->send()->getResponse();
            $this->connection->assertNoFailure($response);
        } catch (Throwable $e) {
            $this->terminateOrRetry($e);
        }

        $this->state = TransactionState::COMMITTED;

        return $tbr;
    }

    public function rollback(): void
    {
        if ($this->isFinished()) {
            if ($this->state === TransactionState::COMMITTED) {
                throw new TransactionException("Can't rollback a committed transaction.");
            }

            if ($this->state === TransactionState::ROLLED_BACK) {
                throw new TransactionException("Can't rollback a rolled back transaction.");
            }
        }

        $this->ensureBeginSent();

        $this->connection->consumeResults();

        try {
            $response = $this->messageFactory->createRollbackMessage()->send()->getResponse();
            $this->connection->assertNoFailure($response);
        } catch (Throwable $e) {
            $this->terminateOrRetry($e);
        }

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
        $parameters = ParameterHelper::formatParameters(
            $statement->getParameters(),
            $this->connection->getProtocol(),
            $this->connection->isBoltUtcPatchNegotiated(),
        );
        $start = microtime(true);

        $serverState = $this->connection->protocol()->serverState;
        if ($serverState === ServerState::STREAMING) {
            $this->connection->consumeResults();
        }

        if ($this->isInstantTransaction) {
            $this->bookmarkTracker?->prepareForSend(true);
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
                $this->isInstantTransaction ? $this->tsxConfig->getMetaData() : null,
                $this->telemetryApi,
            );
        } catch (Throwable $e) {
            $this->terminateOrRetry($e);
        }
        $run = microtime(true);

        $meta ??= ['t_first' => 0];

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

    /**
     * Resets the connection and transaction state for a managed-transaction retry
     * on the same Bolt connection (e.g. after a transient error).
     */
    private function prepareForRetry(): void
    {
        $this->connection->reset();
        $this->state = TransactionState::ACTIVE;
        $this->beginSent = false;
    }

    private function ensureBeginSent(): void
    {
        if ($this->isInstantTransaction || $this->beginSent) {
            return;
        }
        try {
            $this->bookmarkTracker?->prepareForSend(true);
            $this->connection->sendTelemetryIfNeeded($this->telemetryApi);
            $this->connection->begin($this->database, $this->tsxConfig->getTimeout(), $this->bookmarkHolder, $this->tsxConfig->getMetaData());
            $this->beginSent = true;
        } catch (Throwable $e) {
            $this->terminateOrRetry($e);
        }
    }

    private function isTransientError(Throwable $e): bool
    {
        return $e instanceof Neo4jException && $e->getClassification() === 'TransientError';
    }

    private function terminateOrRetry(Throwable $e): void
    {
        if ($this->isTransientError($e)) {
            $this->prepareForRetry();
        } elseif ($this->isSyntaxError($e)) {
            $this->state = TransactionState::TERMINATED;
            throw $e; // syntax errors are semi-recoverable, we don't need to reset anything
        } elseif ($this->isClientError($e)) {
            $this->state = TransactionState::TERMINATED;
            $this->connection->reset();
            throw $e;
        } else {
            $this->state = TransactionState::TERMINATED;
            $this->pool?->release($this->connection);
        }

        throw $e;
    }

    public function isClientError(Throwable $e): bool
    {
        return $e instanceof Neo4jException && $e->getClassification() === 'ClientError';
    }

    private function isSyntaxError(Throwable $e): bool
    {
        return $e instanceof Neo4jException && $e->getTitle() === 'SyntaxError';
    }
}
