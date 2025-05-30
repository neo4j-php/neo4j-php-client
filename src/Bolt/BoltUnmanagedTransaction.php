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
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\TransactionState;
use Laudis\Neo4j\Exception\ClientException;
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
    ) {
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @throws ClientException|Throwable
     *
     * @return CypherList<SummarizedResult>
     */
    public function commit(iterable $statements = []): CypherList
    {
        if ($this->isFinished()) {
            if ($this->state === TransactionState::TERMINATED) {
                throw new ClientException("Can't commit, transaction has been terminated");
            }

            if ($this->state === TransactionState::COMMITTED) {
                throw new ClientException("Can't commit, transaction has already been committed");
            }

            if ($this->state === TransactionState::ROLLED_BACK) {
                throw new ClientException("Can't commit, transaction has already been rolled back");
            }
        }

        // Force the results to pull all the results.
        // After a commit, the connection will be in the ready state, making it impossible to use PULL
        $tbr = $this->runStatements($statements)->each(static function (CypherList $list) {
            $list->preload();
        });

        $this->messageFactory->createCommitMessage($this->bookmarkHolder)->send();
        $this->state = TransactionState::COMMITTED;

        return $tbr;
    }

    public function rollback(): void
    {
        if ($this->isFinished()) {
            if ($this->state === TransactionState::TERMINATED) {
                throw new ClientException("Can't rollback, transaction has been terminated");
            }

            if ($this->state === TransactionState::COMMITTED) {
                throw new ClientException("Can't rollback, transaction has already been committed");
            }

            if ($this->state === TransactionState::ROLLED_BACK) {
                throw new ClientException("Can't rollback, transaction has already been rolled back");
            }
        }

        $this->messageFactory->createRollbackMessage()->send();
        $this->state = TransactionState::ROLLED_BACK;
    }

    /**
     * @throws Throwable
     */
    public function run(string $statement, iterable $parameters = []): SummarizedResult
    {
        return $this->runStatement(new Statement($statement, $parameters));
    }

    /**
     * @throws Throwable
     */
    public function runStatement(Statement $statement): SummarizedResult
    {
        $parameters = ParameterHelper::formatParameters($statement->getParameters(), $this->connection->getProtocol());
        $start = microtime(true);

        $serverState = $this->connection->protocol()->serverState;
        if (in_array($serverState, [ServerState::STREAMING, ServerState::TX_STREAMING])) {
            $this->connection->consumeResults();
        }

        try {
            $meta = $this->connection->run(
                $statement->getText(),
                $parameters->toArray(),
                $this->database,
                $this->tsxConfig->getTimeout(),
                $this->bookmarkHolder,
                $this->config->getAccessMode(),
                $this->tsxConfig->getMetaData()
            );
        } catch (Throwable $e) {
            $this->state = TransactionState::TERMINATED;
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
}
