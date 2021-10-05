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

use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Types\CypherList;

/**
 * An unmanaged transaction needs to be committed or rolled back manually.
 *
 * @template T
 *
 * @extends TransactionInterface<T>
 *
 * @see https://neo4j.com/docs/cypher-manual/current/introduction/transactions/
 */
interface UnmanagedTransactionInterface extends TransactionInterface
{
    /**
     * Runs the final statements provided and then commits the entire transaction.
     *
     * @param iterable<Statement> $statements
     *
     * @return CypherList<T>
     */
    public function commit(iterable $statements = []): CypherList;

    /**
     * Rolls back the transaction.
     */
    public function rollback(): void;
}
