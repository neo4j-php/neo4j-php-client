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

namespace Laudis\Neo4j\Contracts;

use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherList;

/**
 * Transactions are atomic units of work that may contain one or more query.
 *
 * @see https://neo4j.com/docs/cypher-manual/current/introduction/transactions/
 */
interface TransactionInterface
{
    /**
     * @param iterable<string, mixed> $parameters
     */
    public function run(string $statement, iterable $parameters = []): SummarizedResult;

    public function runStatement(Statement $statement): SummarizedResult;

    /**
     * @param iterable<Statement> $statements
     *
     * @throws Neo4jException
     *
     * @return CypherList<SummarizedResult>
     */
    public function runStatements(iterable $statements): CypherList;
}
