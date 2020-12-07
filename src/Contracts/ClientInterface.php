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

namespace Laudis\Neo4j\Contracts;

use Ds\Map;
use Ds\Vector;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;

interface ClientInterface
{
    public const VERSION = '1.0.0-rc1';

    /**
     * Runs a one off transaction with the provided query and parameters over the connection with the provided alias or the master alias othwerise.
     *
     * @param iterable<string, scalar|iterable|null> $parameters
     *
     * @throws Neo4jException
     *
     * @return Vector<Map<string, scalar|array|null>>
     */
    public function run(string $query, iterable $parameters = [], ?string $alias = null): Vector;

    /**
     * Runs a one off transaction with the provided statement over the connection with the provided alias or the master alias othwerise.
     *
     * @throws Neo4jException
     *
     * @return Vector<Map<string, scalar|array|null>>
     */
    public function runStatement(Statement $statement, ?string $alias = null): Vector;

    /**
     * Runs a one off transaction with the provided statements over the connection with the provided alias or the master alias othwerise.
     *
     * @param iterable<Statement> $statements
     *
     * @throws Neo4jException
     *
     * @return Vector<Vector<Map<string, scalar|array|null>>>
     */
    public function runStatements(iterable $statements, ?string $alias = null): Vector;

    /**
     * Opens a transaction over the connection with the given alias if provided, the master alias otherwise.
     *
     * @param iterable<Statement>|null $statements
     *
     * @throws Neo4jException
     */
    public function openTransaction(?iterable $statements = null, ?string $connectionAlias = null): TransactionInterface;
}
