<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Contracts;

use Ds\Vector;
use Laudis\Neo4j\Databags\Statement;

/**
 * @template T
 *
 * @extends ManagedTransactionInterface<T>
 */
interface TransactionInterface extends ManagedTransactionInterface
{
    /**
     * @param iterable<Statement> $statements
     *
     * @return Vector<T>
     */
    public function commit(iterable $statements = []): Vector;

    public function rollback(): void;
}
