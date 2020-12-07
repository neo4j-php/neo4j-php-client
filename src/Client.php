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

namespace Laudis\Neo4j;

use Ds\Map;
use Ds\Vector;
use InvalidArgumentException;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Statement;

final class Client implements ClientInterface
{
    /** @var Map<string, DriverInterface> */
    private Map $connectionPool;
    private string $defaultConnectionAlias;

    /**
     * @param Map<string, DriverInterface> $connectionPool
     */
    public function __construct(Map $connectionPool, string $defaultConnectionAlias)
    {
        $this->connectionPool = $connectionPool;
        $this->defaultConnectionAlias = $defaultConnectionAlias;
    }

    public function run(string $query, iterable $parameters = [], ?string $alias = null): Vector
    {
        return $this->runStatement(new Statement($query, $parameters), $alias);
    }

    public function runStatement(Statement $statement, ?string $alias = null): Vector
    {
        return $this->runStatements([$statement], $alias)->first();
    }

    public function runStatements(iterable $statements, ?string $alias = null): Vector
    {
        $connection = $this->getConnection($alias);
        $session = $connection->aquireSession();

        return $session->run($statements);
    }

    public function openTransaction(?iterable $statements = null, ?string $connectionAlias = null): TransactionInterface
    {
        $connection = $this->getConnection($connectionAlias);

        return $connection->aquireSession()->openTransaction($statements);
    }

    /**
     * @throws InvalidArgumentException
     */
    private function getConnection(?string $connectionAlias): DriverInterface
    {
        $key = $connectionAlias ?? $this->defaultConnectionAlias;
        if (!$this->connectionPool->hasKey($key)) {
            $key = sprintf('The provided alias: "%s" was not found in the connection pool', $key);
            throw new InvalidArgumentException($key);
        }

        return $this->connectionPool->get($key);
    }
}
