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
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherList;

/**
 * @template T
 */
interface SessionInterface
{
    /**
     * @param iterable<Statement> $statements
     *
     * @throws Neo4jException
     *
     * @return CypherList<T>
     */
    public function runStatements(iterable $statements, ?TransactionConfiguration $config = null): CypherList;

    /**
     * @return T
     */
    public function runStatement(Statement $statement, ?TransactionConfiguration $config = null);

    /**
     * @param iterable<string, scalar|iterable|null> $parameters
     *
     * @return T
     */
    public function run(string $statement, iterable $parameters = [], ?TransactionConfiguration $config = null);

    /**
     * @psalm-param iterable<Statement>|null $statements
     *
     * @throws Neo4jException
     *
     * @deprecated
     * @see SessionInterface::beginTransaction use this method instead.
     *
     * @return UnmanagedTransactionInterface<T>
     */
    public function openTransaction(?iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface;

    /**
     * @psalm-param iterable<Statement>|null $statements
     *
     * @throws Neo4jException
     *
     * @return UnmanagedTransactionInterface<T>
     */
    public function beginTransaction(?iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface;

    /**
     * @template U
     *
     * @param callable(TransactionInterface<T>):U $tsxHandler
     *
     * @return U
     */
    public function writeTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null);

    /**
     * @template U
     *
     * @param callable(TransactionInterface<T>):U $tsxHandler
     *
     * @return U
     */
    public function readTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null);

    /**
     * @template U
     *
     * @param callable(TransactionInterface<T>):U $tsxHandler
     *
     * @return U
     */
    public function transaction(callable $tsxHandler, ?TransactionConfiguration $config = null);
}
