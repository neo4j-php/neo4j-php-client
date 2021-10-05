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
use Bolt\error\MessageException;
use Exception;
use Laudis\Neo4j\Common\TransactionHelper;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Neo4jError;
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
     * @var ConnectionInterface<Bolt>
     */
    private ConnectionInterface $connection;
    /** @psalm-readonly */
    private string $database;
    private bool $finished = false;

    /**
     * @param FormatterInterface<T>     $formatter
     * @param ConnectionInterface<Bolt> $connection
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

        if ($this->finished) {
            throw new Neo4jException([new Neo4jError('0', 'Transaction already finished')]);
        }

        try {
            $this->getBolt()->commit();
            $this->finished = true;
        } catch (Exception $e) {
            $code = TransactionHelper::extractCode($e);
            throw new Neo4jException([new Neo4jError($code ?? '', $e->getMessage())], $e);
        }

        return $tbr;
    }

    public function rollback(): void
    {
        if ($this->finished) {
            throw new Neo4jException([new Neo4jError('0', 'Transaction already finished')]);
        }

        try {
            $this->connection->getImplementation()->rollback();
            $this->finished = true;
        } catch (Exception $e) {
            $code = TransactionHelper::extractCode($e) ?? '';
            throw new Neo4jException([new Neo4jError($code, $e->getMessage())], $e);
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
            try {
                $start = microtime(true);
                /** @var BoltMeta $meta */
                $meta = $this->getBolt()->run($statement->getText(), $parameters->toArray(), $extra);
                $run = microtime(true);
                /** @var array<array> $results */
                $results = $this->getBolt()->pullAll();
                $end = microtime(true);
            } catch (Throwable $e) {
                if ($e instanceof MessageException) {
                    $code = TransactionHelper::extractCode($e) ?? '';
                    throw new Neo4jException([new Neo4jError($code, $e->getMessage())], $e);
                }
                throw $e;
            }
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
    private function getBolt(): Bolt
    {
        return $this->connection->getImplementation();
    }

    public function __destruct()
    {
        $this->connection->close();
    }
}
