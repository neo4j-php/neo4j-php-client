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

namespace Laudis\Neo4j\HttpDriver;

use Ds\Vector;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Statement;

final class Transaction implements TransactionInterface
{
    private SessionInterface $session;
    private string $endpoint;

    public function __construct(SessionInterface $session, string $endpoint)
    {
        $this->session = $session;
        $this->endpoint = $endpoint;
    }

    public function commit(iterable $statements = []): Vector
    {
        return $this->session->commitTransaction($this, $statements);
    }

    public function rollback(): void
    {
        $this->session->rollbackTransaction($this);
    }

    public function run(string $statement, iterable $parameters = []): Vector
    {
        return $this->runStatement(Statement::create($statement, $parameters));
    }

    public function runStatement(Statement $statement): Vector
    {
        return $this->runStatements([$statement])->first();
    }

    public function runStatements(iterable $statements): Vector
    {
        return $this->session->runOverTransaction($this, $statements);
    }

    public function getDomainIdentifier(): string
    {
        return $this->endpoint;
    }
}
