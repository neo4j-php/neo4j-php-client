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
use Ds\Vector;
use Exception;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\ParameterHelper;
use Laudis\Neo4j\Types\CypherList;
use Throwable;

/**
 * @template T
 *
 * @implements UnmanagedTransactionInterface<T>
 *
 * @psalm-import-type BoltMeta from \Laudis\Neo4j\Contracts\FormatterInterface
 */
final class BoltUnmanagedTransaction implements UnmanagedTransactionInterface
{
    private FormatterInterface $formatter;
    private Bolt $bolt;
    private string $database;
    private bool $finished = false;

    /**
     * @param FormatterInterface<T> $formatter
     */
    public function __construct(string $database, FormatterInterface $formatter, Bolt $bolt)
    {
        $this->formatter = $formatter;
        $this->bolt = $bolt;
        $this->database = $database;
    }

    public function commit(iterable $statements = []): CypherList
    {
        $tbr = $this->runStatements($statements);

        if ($this->finished) {
            throw new Neo4jException(new Vector([new Neo4jError('0', 'Transaction already finished')]));
        }

        try {
            $this->bolt->commit();
            $this->finished = true;
        } catch (Exception $e) {
            throw new Neo4jException(new Vector([new Neo4jError('', $e->getMessage())]), $e);
        }

        return $tbr;
    }

    public function rollback(): void
    {
        if ($this->finished) {
            throw new Neo4jException(new Vector([new Neo4jError('0', 'Transaction already finished')]));
        }

        try {
            $this->bolt->rollback();
            $this->finished = true;
        } catch (Exception $e) {
            throw new Neo4jException(new Vector([new Neo4jError('', $e->getMessage())]), $e);
        }
    }

    public function run(string $statement, iterable $parameters = [])
    {
        return $this->runStatement(new Statement($statement, $parameters));
    }

    public function runStatement(Statement $statement)
    {
        return $this->runStatements([$statement])->first();
    }

    public function runStatements(iterable $statements): CypherList
    {
        /** @var Vector<T> $tbr */
        $tbr = new Vector();
        foreach ($statements as $statement) {
            $extra = ['db' => $this->database];
            $parameters = ParameterHelper::formatParameters($statement->getParameters());
            try {
                /** @var BoltMeta $meta */
                $meta = $this->bolt->run($statement->getText(), $parameters->toArray(), $extra);
                /** @var array<array> $results */
                $results = $this->bolt->pullAll();
            } catch (Throwable $e) {
                throw new Neo4jException(new Vector([new Neo4jError('', $e->getMessage())]), $e);
            }
            $tbr->push($this->formatter->formatBoltResult($meta, $results, $this->bolt));
        }

        return new CypherList($tbr);
    }
}
