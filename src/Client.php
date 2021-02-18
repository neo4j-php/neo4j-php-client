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
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Statement;

/**
 * @template T
 * @implements ClientInterface<T>
 */
final class Client implements ClientInterface
{
    /** @var Map<string, DriverInterface> */
    private Map $connectionPool;
    private string $defaultConnectionAlias;
    private FormatterInterface $formatter;

    /**
     * @param FormatterInterface<T>        $formatter
     * @param Map<string, DriverInterface> $connectionPool
     */
    public function __construct(Map $connectionPool, string $defaultConnectionAlias, FormatterInterface $formatter)
    {
        $this->connectionPool = $connectionPool;
        $this->defaultConnectionAlias = $defaultConnectionAlias;
        $this->formatter = $formatter;
    }

    public function run(string $query, iterable $parameters = [], ?string $alias = null)
    {
        return $this->runStatement(new Statement($query, $parameters), $alias);
    }

    public function runStatement(Statement $statement, ?string $alias = null)
    {
        return $this->runStatements([$statement], $alias)->first();
    }

    public function runStatements(iterable $statements, ?string $alias = null): Vector
    {
        $connection = $this->getConnection($alias);
        $session = $connection->aquireSession($this->formatter);

        return $session->run($statements);
    }

    public function openTransaction(?iterable $statements = null, ?string $connectionAlias = null): TransactionInterface
    {
        $connection = $this->getConnection($connectionAlias);

        return $connection->aquireSession($this->formatter)->openTransaction($statements);
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

    public function withFormatter(FormatterInterface $formatter): ClientInterface
    {
        return new self($this->connectionPool, $this->defaultConnectionAlias, $formatter);
    }

    public function getFormatter(): FormatterInterface
    {
        return $this->formatter;
    }
}
