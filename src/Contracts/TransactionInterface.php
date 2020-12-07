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

interface TransactionInterface
{
    /**
     * @param iterable<Statement> $statements
     *
     * @return Vector<Vector<Map<string, scalar|array|null>>>
     */
    public function commit(iterable $statements = []): Vector;

    public function rollback(): void;

    /**
     * @param iterable<string, scalar|iterable|null> $parameters
     *
     * @return Vector<Map<string, scalar|array|null>>
     */
    public function run(string $statement, iterable $parameters = []): Vector;

    /**
     * @return Vector<Map<string, scalar|array|null>>
     */
    public function runStatement(Statement $statement): Vector;

    /**
     * @param iterable<Statement> $statements
     *
     * @throws Neo4jException
     *
     * @return Vector<Vector<Map<string, scalar|array|null>>>
     */
    public function runStatements(iterable $statements): Vector;

    public function getDomainIdentifier(): string;
}
