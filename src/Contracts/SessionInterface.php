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

interface SessionInterface
{
    /**
     * @param iterable<Statement> $statements
     *
     * @throws Neo4jException
     *
     * @return Vector<Vector<Map<string, scalar|array|null>>>
     */
    public function run(iterable $statements): Vector;

    /**
     * @param iterable<Statement> $statements
     *
     * @throws Neo4jException
     *
     * @return Vector<Vector<Map<string, scalar|array|null>>>
     */
    public function runOverTransaction(TransactionInterface $transaction, iterable $statements): Vector;

    /**
     * @throws Neo4jException
     */
    public function rollbackTransaction(TransactionInterface $transaction): void;

    /**
     * @param iterable<Statement> $statements
     *
     * @throws Neo4jException
     *
     * @return Vector<Vector<Map<string, scalar|array|null>>>
     */
    public function commitTransaction(TransactionInterface $transaction, iterable $statements): Vector;

    /**
     * @psalm-param iterable<Statement>|null $statements
     *
     * @throws Neo4jException
     */
    public function openTransaction(?iterable $statements = null): TransactionInterface;
}
