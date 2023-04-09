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

use Laudis\Neo4j\Common\TransactionHelper;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\ParameterHelper;
use Laudis\Neo4j\Types\AbstractCypherSequence;
use Laudis\Neo4j\Types\CypherList;

use function microtime;

use Throwable;

/**
 * Manages a transaction over the bolt protocol.
 *
 * @template T
 *
 * @implements UnmanagedTransactionInterface<T>
 *
 * @psalm-import-type BoltMeta from FormatterInterface
 */
final class BoltUnmanagedTransaction implements UnmanagedTransactionInterface
{
    private bool $isRolledBack = false;

    private bool $isCommitted = false;

    /**
     * @param FormatterInterface<T> $formatter
     */
    public function __construct(
        /** @psalm-readonly */
        private ?string $database,
        /**
         * @psalm-readonly
         */
        private FormatterInterface $formatter,
        /** @psalm-readonly */
        private BoltConnection $connection,
        private SessionConfiguration $config,
        private TransactionConfiguration $tsxConfig,
        private BookmarkHolder $bookmarkHolder
    ) {}

    public function commit(iterable $statements = []): CypherList
    {
        // Force the results to pull all the results.
        // After a commit, the connection will be in the ready state, making it impossible to use PULL
        $tbr = $this->runStatements($statements)->each(static function ($list) {
            if ($list instanceof AbstractCypherSequence) {
                $list->preload();
            }
        });

        $this->connection->commit();
        $this->isCommitted = true;

        return $tbr;
    }

    public function rollback(): void
    {
        $this->connection->rollback();
        $this->isRolledBack = true;
    }

    /**
     * @throws Throwable
     */
    public function run(string $statement, iterable $parameters = [])
    {
        return $this->runStatement(new Statement($statement, $parameters));
    }

    /**
     * @throws Throwable
     */
    public function runStatement(Statement $statement)
    {
        $parameters = ParameterHelper::formatParameters($statement->getParameters(), $this->connection->getProtocol());
        $start = microtime(true);

        try {
            $meta = $this->connection->run(
                $statement->getText(),
                $parameters->toArray(),
                $this->database,
                $this->tsxConfig->getTimeout(),
                $this->bookmarkHolder
            );
        } catch (Throwable $e) {
            $this->isRolledBack = true;
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
     * @throws Throwable
     */
    public function runStatements(iterable $statements): CypherList
    {
        /** @var list<T> $tbr */
        $tbr = [];
        foreach ($statements as $statement) {
            $tbr[] = $this->runStatement($statement);
        }

        return new CypherList($tbr);
    }

    /**
     * @throws Neo4jException
     *
     * @return never
     */
    private function handleMessageException(Neo4jException $e): void
    {
        $exception = $e->getErrors()[0];
        if (!($exception->getClassification() === 'ClientError' && $exception->getCategory() === 'Request')) {
            $this->connection->reset();
        }
        if (!$this->isFinished() && in_array($exception->getClassification(), TransactionHelper::ROLLBACK_CLASSIFICATIONS)) {
            $this->isRolledBack = true;
        }

        throw $e;
    }

    public function isRolledBack(): bool
    {
        return $this->isRolledBack;
    }

    public function isCommitted(): bool
    {
        return $this->isCommitted;
    }

    public function isFinished(): bool
    {
        return $this->isRolledBack() || $this->isCommitted();
    }
}
