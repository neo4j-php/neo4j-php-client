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

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;

/**
 * @implements ClientInterface<SummarizedResult<CypherMap>>
 */
final class Client implements ClientInterface
{
    /**
     * @param ClientInterface<SummarizedResult<CypherMap>> $client
     */
    public function __construct(
        private readonly ClientInterface $client
    ) {}

    public function run(string $statement, iterable $parameters = [], ?string $alias = null): SummarizedResult
    {
        return $this->client->run($statement, $parameters, $alias);
    }

    public function runStatement(Statement $statement, ?string $alias = null): SummarizedResult
    {
        return $this->client->runStatement($statement, $alias);
    }

    public function runStatements(iterable $statements, ?string $alias = null): CypherList
    {
        return $this->client->runStatements($statements, $alias);
    }

    public function beginTransaction(?iterable $statements = null, ?string $alias = null, ?TransactionConfiguration $config = null): UnmanagedTransaction
    {
        return new UnmanagedTransaction($this->client->beginTransaction($statements, $alias, $config));
    }

    public function getDriver(?string $alias): Driver
    {
        $driver = $this->client->getDriver($alias);
        if ($driver instanceof Driver) {
            return $driver;
        }

        return new Driver($driver);
    }

    public function hasDriver(string $alias): bool
    {
        return $this->client->hasDriver($alias);
    }

    public function writeTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        return $this->client->writeTransaction($tsxHandler, $alias, $config);
    }

    public function readTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        return $this->client->readTransaction($tsxHandler, $alias, $config);
    }

    public function transaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        return $this->client->transaction($tsxHandler, $alias, $config);
    }

    public function verifyConnectivity(?string $driver = null): bool
    {
        return $this->client->verifyConnectivity($driver);
    }

    public function bindTransaction(?string $alias = null, ?TransactionConfiguration $config = null): void
    {
        $this->client->bindTransaction($alias, $config);
    }

    public function commitBoundTransaction(?string $alias = null, int $depth = 1): void
    {
        $this->client->commitBoundTransaction($alias, $depth);
    }

    public function rollbackBoundTransaction(?string $alias = null, int $depth = 1): void
    {
        $this->client->rollbackBoundTransaction($alias, $depth);
    }
}
