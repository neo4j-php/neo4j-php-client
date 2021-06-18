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
 * @template T
 *
 * @extends TransactionInterface<T>
 */
interface UnmanagedTransactionInterface extends TransactionInterface
{
    /**
     * @param iterable<Statement> $statements
     *
     * @return CypherList<T>
     */
    public function commit(iterable $statements = []): CypherList;

    public function rollback(): void;
}
