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

use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherList;

/**
 * A lightweight container for causally chained sequences of transactions to carry out work.
 *
 * @template ResultFormat
 *
 * @extends TransactionInterface<ResultFormat>
 */
interface SessionInterface extends TransactionInterface
{
    /**
     * @param iterable<Statement> $statements
     *
     * @throws Neo4jException
     *
     * @return CypherList<ResultFormat>
     */
    public function runStatements(iterable $statements, ?TransactionConfiguration $config = null): CypherList;

    /**
     * @return ResultFormat
     */
    public function runStatement(Statement $statement, ?TransactionConfiguration $config = null);

    /**
     * @param iterable<string, mixed> $parameters
     *
     * @return ResultFormat
     */
    public function run(string $statement, iterable $parameters = [], ?TransactionConfiguration $config = null);

    /**
     * @psalm-param iterable<Statement>|null $statements
     *
     * @throws Neo4jException
     *
     * @return UnmanagedTransactionInterface<ResultFormat>
     */
    public function beginTransaction(?iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface;

    /**
     * @template HandlerResult
     *
     * @param callable(TransactionInterface<ResultFormat>):HandlerResult $tsxHandler
     *
     * @return HandlerResult
     */
    public function writeTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null);

    /**
     * @template HandlerResult
     *
     * @param callable(TransactionInterface<ResultFormat>):HandlerResult $tsxHandler
     *
     * @return HandlerResult
     */
    public function readTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null);

    /**
     * @template HandlerResult
     *
     * @param callable(TransactionInterface<ResultFormat>):HandlerResult $tsxHandler
     *
     * @return HandlerResult
     */
    public function transaction(callable $tsxHandler, ?TransactionConfiguration $config = null);

    public function getLastBookmark(): Bookmark;
}
