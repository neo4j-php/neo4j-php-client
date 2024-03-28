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

namespace Laudis\Neo4j\Basic;

use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;

/**
 * @implements SessionInterface<SummarizedResult<CypherMap>>
 */
final class Session implements SessionInterface
{
    /**
     * @param SessionInterface<SummarizedResult<CypherMap>> $session
     */
    public function __construct(
        private readonly SessionInterface $session
    ) {}

    /**
     * @param iterable<Statement> $statements
     *
     * @return CypherList<SummarizedResult<CypherMap>>
     */
    public function runStatements(iterable $statements, ?TransactionConfiguration $config = null): CypherList
    {
        return $this->session->runStatements($statements, $config);
    }

    /**
     * @return SummarizedResult<CypherMap>
     */
    public function runStatement(Statement $statement, ?TransactionConfiguration $config = null): SummarizedResult
    {
        return $this->session->runStatement($statement, $config);
    }

    /**
     * @param iterable<string, mixed> $parameters
     *
     * @return SummarizedResult<CypherMap>
     */
    public function run(string $statement, iterable $parameters = [], ?TransactionConfiguration $config = null): SummarizedResult
    {
        return $this->session->run($statement, $parameters, $config);
    }

    public function beginTransaction(?iterable $statements = null, ?TransactionConfiguration $config = null): UnmanagedTransaction
    {
        return new UnmanagedTransaction($this->session->beginTransaction($statements, $config));
    }

    public function writeTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return $this->session->writeTransaction($tsxHandler, $config);
    }

    public function readTransaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return $this->session->readTransaction($tsxHandler, $config);
    }

    public function transaction(callable $tsxHandler, ?TransactionConfiguration $config = null)
    {
        return $this->session->writeTransaction($tsxHandler, $config);
    }

    public function getLastBookmark(): Bookmark
    {
        return $this->session->getLastBookmark();
    }
}
