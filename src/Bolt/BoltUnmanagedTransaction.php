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

use Bolt\error\ConnectionTimeoutException;
use Bolt\error\MessageException;
use Bolt\protocol\V3;
use Laudis\Neo4j\Common\TransactionHelper;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\ParameterHelper;
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
 * @psalm-import-type BoltMeta from \Laudis\Neo4j\Contracts\FormatterInterface
 */
final class BoltUnmanagedTransaction implements UnmanagedTransactionInterface
{
    /**
     * @psalm-readonly
     *
     * @var FormatterInterface<T>
     */
    private FormatterInterface $formatter;
    /**
     * @psalm-readonly
     *
     * @var ConnectionInterface<V3>
     */
    private ConnectionInterface $connection;
    /** @psalm-readonly */
    private string $database;

    private bool $isRolledBack = false;

    private bool $isCommitted = false;

    /**
     * @param FormatterInterface<T>   $formatter
     * @param ConnectionInterface<V3> $connection
     *
     * @psalm-mutation-free
     */
    public function __construct(string $database, FormatterInterface $formatter, ConnectionInterface $connection)
    {
        $this->formatter = $formatter;
        $this->connection = $connection;
        $this->database = $database;
    }

    public function commit(iterable $statements = []): CypherList
    {
        $tbr = $this->runStatements($statements);

        try {
            $this->getBolt()->commit();
            $this->isCommitted = true;
        } catch (MessageException $e) {
            $this->handleMessageException($e);
        } catch (ConnectionTimeoutException $e) {
            $this->handleConnectionTimeoutException($e);
        }

        return $tbr;
    }

    public function rollback(): void
    {
        try {
            $this->connection->getImplementation()->rollback();
            $this->isRolledBack = true;
        } catch (MessageException $e) {
            $this->handleMessageException($e);
        } catch (ConnectionTimeoutException $e) {
            $this->handleConnectionTimeoutException($e);
        }
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
        return $this->runStatements([$statement])->first();
    }

    /**
     * @throws Throwable
     */
    public function runStatements(iterable $statements): CypherList
    {
        /** @var list<T> $tbr */
        $tbr = [];
        foreach ($statements as $statement) {
            $extra = ['db' => $this->database];
            $parameters = ParameterHelper::formatParameters($statement->getParameters());
            $start = microtime(true);

            try {
                /** @var BoltMeta $meta */
                $meta = $this->getBolt()->run($statement->getText(), $parameters->toArray(), $extra);
                $run = microtime(true);
                /** @var array<array> $results */
                $results = $this->getBolt()->pullAll();
            } catch (MessageException $e) {
                $this->handleMessageException($e);
            } catch (ConnectionTimeoutException $e) {
                $this->handleConnectionTimeoutException($e);
            }

            $end = microtime(true);
            $tbr[] = $this->formatter->formatBoltResult(
                $meta,
                $results,
                $this->connection,
                $run - $start,
                $end - $start,
                $statement
            );
        }

        return new CypherList($tbr);
    }

    /**
     * @psalm-immutable
     */
    private function getBolt(): V3
    {
        return $this->connection->getImplementation();
    }

    public function __destruct()
    {
        $this->connection->close();
    }

    /**
     * @throws Neo4jException
     *
     * @return never
     */
    private function handleMessageException(MessageException $e): void
    {
        $exception = Neo4jException::fromMessageException($e);
        if (!$this->isFinished() && in_array($exception->getClassification(), TransactionHelper::ROLLBACK_CLASSIFICATIONS)) {
            $this->isRolledBack = true;
        }
        throw $exception;
    }

    /**
     * @throws ConnectionTimeoutException
     *
     * @return never
     */
    private function handleConnectionTimeoutException(ConnectionTimeoutException $e): void
    {
        $this->connection->reset();

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
