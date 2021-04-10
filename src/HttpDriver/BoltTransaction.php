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

namespace Laudis\Neo4j\HttpDriver;

use Bolt\Bolt;
use Ds\Vector;
use Exception;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\ParameterHelper;
use Throwable;

/**
 * @template T
 *
 * @implements TransactionInterface<T>
 */
final class BoltTransaction implements TransactionInterface
{
    private Bolt $bolt;
    private SessionInterface $session;

    /**
     * @param SessionInterface<T> $session
     */
    public function __construct(Bolt $bolt, SessionInterface $session)
    {
        $this->bolt = $bolt;
        $this->session = $session;
    }

    public function commit(iterable $statements = []): Vector
    {
        $tbr = $this->runStatements($statements);

        try {
            $this->bolt->commit();
        } catch (Exception $e) {
            throw new Neo4jException(new Vector([new Neo4jError('', $e->getMessage())]), $e);
        }

        return $tbr;
    }

    public function rollback(): void
    {
        try {
            $this->bolt->rollback();
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

    public function runStatements(iterable $statements): Vector
    {
        /** @var Vector<T> $tbr */
        $tbr = new Vector();
        foreach ($statements as $statement) {
            $extra = ['db' => $this->session->getConfig()->getDatabase()];
            $parameters = ParameterHelper::formatParameters($statement->getParameters());
            try {
                /** @var array{fields: array<int, string>} $meta */
                $meta = $this->bolt->run($statement->getText(), $parameters->toArray(), $extra);
                /** @var array<array> $results */
                $results = $this->bolt->pullAll();
            } catch (Throwable $e) {
                throw new Neo4jException(new Vector([new Neo4jError('', $e->getMessage())]), $e);
            }
            $tbr->push($this->session->getFormatter()->formatBoltResult($meta, $results, $this->bolt));
        }

        return $tbr;
    }
}
