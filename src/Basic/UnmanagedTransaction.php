<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Basic;

use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;

/**
 * @implements UnmanagedTransactionInterface<SummarizedResult<CypherMap>>
 */
final class UnmanagedTransaction implements UnmanagedTransactionInterface
{
    /** @var UnmanagedTransactionInterface<SummarizedResult<CypherMap>> */
    private UnmanagedTransactionInterface $tsx;

    /**
     * @param UnmanagedTransactionInterface<SummarizedResult<CypherMap>> $tsx
     */
    public function __construct(UnmanagedTransactionInterface $tsx)
    {
        $this->tsx = $tsx;
    }

    /**
     * @param iterable<string, mixed> $parameters
     *
     * @return SummarizedResult<CypherMap>
     */
    public function run(string $statement, iterable $parameters = []): SummarizedResult
    {
        return $this->tsx->run($statement, $parameters);
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @return SummarizedResult<CypherMap>
     */
    public function runStatement(Statement $statement): SummarizedResult
    {
        return $this->tsx->runStatement($statement);
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @return CypherList<SummarizedResult<CypherMap>>
     */
    public function runStatements(iterable $statements): CypherList
    {
        return $this->tsx->runStatements($statements);
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @return CypherList<SummarizedResult<CypherMap>>
     */
    public function commit(iterable $statements = []): CypherList
    {
        return $this->tsx->commit($statements);
    }

    public function rollback(): void
    {
        $this->tsx->rollback();
    }

    public function isCommitted(): bool
    {
        return $this->tsx->isCommitted();
    }

    public function isRolledBack(): bool
    {
        return $this->tsx->isRolledBack();
    }

    public function isFinished(): bool
    {
        return $this->tsx->isRolledBack();
    }
}
