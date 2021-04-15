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
use Laudis\Neo4j\Databags\StaticTransactionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
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
    public function runStatements(iterable $statements, ?TransactionConfiguration $config = null): Vector;

    /**
     * @return T
     */
    public function runStatement(Statement $statement, ?TransactionConfiguration $config = null);

    /**
     * @param iterable<string, scalar|iterable|null> $parameters
     *
     * @return T
     */
    public function run(string $statement, iterable $parameters, ?TransactionConfiguration $config = null);

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

    public function getConfig(): SessionConfiguration;

    /**
     * @return StaticTransactionConfiguration<T>
     */
    public function getTransactionConfig(): StaticTransactionConfiguration;

    /**
     * @template U
     *
     * @param callable():FormatterInterface<U>|FormatterInterface<U> $formatter
     *
     * @return self<U>
     */
    public function withFormatter($formatter): self;

    /**
     * @param callable():(float|null)|float|null $timeout
     *
     * @return self<T>
     */
    public function withTransactionTimeout($timeout): self;

    /**
     * @param callable():(string|null)|string|null $database
     *
     * @return self<T>
     */
    public function withDatabase($database): self;

    /**
     * @param callable():(int|null)|int|null $fetchSize
     *
     * @return self<T>
     */
    public function withFetchSize($fetchSize): self;

    /**
     * @param callable():(\Laudis\Neo4j\Enum\AccessMode|null)|\Laudis\Neo4j\Enum\AccessMode|null $accessMode
     *
     * @return self<T>
     */
    public function withAccessMode($accessMode): self;

    /**
     * @param callable():(iterable<string>|null)|null $bookmarks
     *
     * @return self<T>
     */
    public function withBookmarks($bookmarks): self;

    /**
     * @return self<T>
     */
    public function withConfiguration(SessionConfiguration $configuration): self;

    /**
     * @return self<T>
     */
    public function withTransactionConfiguration(?TransactionConfiguration $configuration): self;
}
