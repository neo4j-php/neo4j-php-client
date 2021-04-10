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

use Ds\Vector;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfig;
use Laudis\Neo4j\Exception\Neo4jException;

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
     * @return Vector<T>
     */
    public function runStatements(iterable $statements, ?TransactionConfig $config = null): Vector;

    /**
     * @return T
     */
    public function runStatement(Statement $statement, ?TransactionConfig $config = null);

    /**
     * @param iterable<string, scalar|iterable|null> $parameters
     * @return T
     */
    public function run(string $statement, iterable $parameters, ?TransactionConfig $config = null);

    /**
     * @psalm-param iterable<Statement>|null $statements
     *
     * @throws Neo4jException
     *
     * @deprecated
     * @see SessionInterface::beginTransaction use this method instead.
     */
    public function openTransaction(?iterable $statements = null, ?TransactionConfig $config = null): TransactionInterface;

    /**
     * @psalm-param iterable<Statement>|null $statements
     *
     * @throws Neo4jException
     */
    public function beginTransaction(?iterable $statements = null, ?TransactionConfig $config = null): TransactionInterface;

    /**
     * @template U
     *
     * @param callable(ManagedTransactionInterface<T>):U $tsxHandler
     *
     * @return U
     */
    public function writeTransaction(callable $tsxHandler, ?TransactionConfig $config = null);

    /**
     * @template U
     *
     * @param callable(ManagedTransactionInterface<T>):U $tsxHandler
     *
     * @return U
     */
    public function readTransaction(callable $tsxHandler, ?TransactionConfig $config = null);

    /**
     * Alias for write transaction.
     *
     * @template U
     *
     * @param callable(ManagedTransactionInterface<T>):U $tsxHandler
     *
     * @return U
     */
    public function transaction(callable $tsxHandler, ?TransactionConfig $config = null);

    public function getConfig(): SessionConfiguration;

    public function getFormatter(): FormatterInterface;

    public function withFormatter(FormatterInterface $formatter): self;
}
