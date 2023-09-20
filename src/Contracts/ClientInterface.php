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
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherList;

/**
 * @template ResultFormat
 *
 * @extends TransactionInterface<ResultFormat>
 */
interface ClientInterface extends TransactionInterface
{
    /**
     * Runs a one off transaction with the provided query and parameters over the connection with the provided alias or the master alias otherwise.
     *
     * @param iterable<string, mixed> $parameters
     *
     * @throws Neo4jException
     *
     * @return ResultFormat
     */
    public function run(string $statement, iterable $parameters = [], ?string $alias = null);

    /**
     * Runs a one off transaction with the provided statement over the connection with the provided alias or the master alias otherwise.
     *
     * @throws Neo4jException
     *
     * @return ResultFormat
     */
    public function runStatement(Statement $statement, ?string $alias = null);

    /**
     * Runs a one off transaction with the provided statements over the connection with the provided alias or the master alias otherwise.
     *
     * @param iterable<Statement> $statements
     *
     * @throws Neo4jException
     *
     * @return CypherList<ResultFormat>
     */
    public function runStatements(iterable $statements, ?string $alias = null): CypherList;

    /**
     * Opens a transaction over the connection with the given alias if provided, the master alias otherwise.
     *
     * @param iterable<Statement>|null $statements
     *
     * @throws Neo4jException
     *
     * @return UnmanagedTransactionInterface<ResultFormat>
     */
    public function beginTransaction(?iterable $statements = null, ?string $alias = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface;

    /**
     * Gets the driver with the provided alias. Gets the default driver if no alias is provided.
     *
     * The driver is guaranteed to have its connectivity verified at least once during its lifetime.
     *
     * @return DriverInterface<ResultFormat>
     */
    public function getDriver(?string $alias): DriverInterface;

    /**
     * Checks to see if the Client has the driver registered with the provided alias.
     */
    public function hasDriver(string $alias): bool;

    /**
     * @template U
     *
     * @param callable(TransactionInterface<ResultFormat>):U $tsxHandler
     *
     * @return U
     */
    public function writeTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null);

    /**
     * @template U
     *
     * @param callable(TransactionInterface<ResultFormat>):U $tsxHandler
     *
     * @return U
     */
    public function readTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null);

    /**
     * Alias for write transaction.
     *
     * @template U
     *
     * @param callable(TransactionInterface<ResultFormat>):U $tsxHandler
     *
     * @return U
     */
    public function transaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null);

    /**
     * Checks to see if the driver can make a valid connection to the configured neo4j server.
     */
    public function verifyConnectivity(?string $driver = null): bool;

    /**
     * Binds a transaction to the client, so it runs all subsequent queries on the latest transaction instead of a session or the previously bound transaction.
     */
    public function bindTransaction(?string $alias = null, ?TransactionConfiguration $config = null): void;

    /**
     * Release a transaction from the client by committing it, so it runs all subsequent queries on a session or the previously bound transaction instead of the latest transaction. You can control the amount of transactions to be released by the depth parameter, with -1 being all transactions.
     */
    public function commitBoundTransaction(?string $alias = null, int $depth = 1): void;

    /**
     * Release a transaction from the client by rolling it back, so it runs all subsequent queries on a session or the previously bound transaction instead of the latest transaction. You can control the amount of transactions to be released by the depth parameter, with -1 being all transactions.
     */
    public function rollbackBoundTransaction(?string $alias = null, int $depth = 1): void;
}
