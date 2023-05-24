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

namespace Laudis\Neo4j;

use Laudis\Neo4j\Common\DriverSetupManager;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Types\CypherList;

/**
 * A collection of drivers with methods to run queries though them.
 *
 * @template ResultFormat
 *
 * @implements ClientInterface<ResultFormat>
 */
final class Client implements ClientInterface
{
    /**
     * @var array<string, list<UnmanagedTransactionInterface<ResultFormat>>>
     */
    private array $boundTransactions = [];

    /**
     * @psalm-mutation-free
     *
     * @param DriverSetupManager<ResultFormat> $driverSetups
     */
    public function __construct(
        private DriverSetupManager $driverSetups,
        private SessionConfiguration $defaultSessionConfiguration,
        private TransactionConfiguration $defaultTransactionConfiguration
    ) {}

    public function run(string $statement, iterable $parameters = [], ?string $alias = null)
    {
        return $this->runStatement(Statement::create($statement, $parameters), $alias);
    }

    public function runStatement(Statement $statement, ?string $alias = null)
    {
        return $this->runStatements([$statement], $alias)->first();
    }

    public function runStatements(iterable $statements, ?string $alias = null): CypherList
    {
        $session = $this->startSession($alias, $this->defaultSessionConfiguration);

        return $session->runStatements($statements, $this->defaultTransactionConfiguration);
    }

    public function beginTransaction(?iterable $statements = null, ?string $alias = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        $session = $this->startSession($alias, $this->defaultSessionConfiguration);
        $config = $this->getTsxConfig($config);

        return $session->beginTransaction($statements, $config);
    }

    public function getDriver(?string $alias): DriverInterface
    {
        return $this->driverSetups->getDriver($this->defaultSessionConfiguration, $alias);
    }

    /**
     * @return SessionInterface<ResultFormat>
     */
    private function startSession(?string $alias, SessionConfiguration $configuration): SessionInterface
    {
        return $this->getDriver($alias)->createSession($configuration);
    }

    public function writeTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        $sessionConfig = $this->defaultSessionConfiguration->withAccessMode(AccessMode::WRITE());
        $startSession = $this->startSession($alias, $sessionConfig);

        return $startSession->writeTransaction($tsxHandler, $this->getTsxConfig($config));
    }

    public function readTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        $sessionConfig = $this->defaultSessionConfiguration->withAccessMode(AccessMode::READ());
        $session = $this->startSession($alias, $sessionConfig);

        return $session->readTransaction($tsxHandler, $this->getTsxConfig($config));
    }

    public function transaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        return $this->writeTransaction($tsxHandler, $alias, $config);
    }

    public function verifyConnectivity(?string $driver = null): bool
    {
        return $this->driverSetups->verifyConnectivity($this->defaultSessionConfiguration, $driver);
    }

    public function bindTransaction(?string $alias = null, ?TransactionConfiguration $config = null): void
    {
        $alias ??= $this->driverSetups->getDefaultAlias();

        $this->boundTransactions[$alias] ??= [];
        $this->boundTransactions[$alias][] = $this->beginTransaction(null, $alias, $config);
    }

    public function rollbackBoundTransaction(?string $alias = null, int $depth = 1): void
    {
        $this->popTransactions(static fn (UnmanagedTransactionInterface $tsx) => $tsx->rollback(), $alias, $depth);
    }

    /**
     * @param callable(UnmanagedTransactionInterface<ResultFormat>): void $handler
     */
    private function popTransactions(callable $handler, ?string $alias = null, int $depth = 1): void
    {
        $alias ??= $this->driverSetups->getDefaultAlias();

        if (!array_key_exists($alias, $this->boundTransactions)) {
            return;
        }

        while (count($this->boundTransactions[$alias]) !== 0 && $depth !== 0) {
            $tsx = array_pop($this->boundTransactions[$alias]);
            $handler($tsx);
            --$depth;
        }
    }

    public function commitBoundTransaction(?string $alias = null, int $depth = 1): void
    {
        $this->popTransactions(static fn (UnmanagedTransactionInterface $tsx) => $tsx->commit(), $alias, $depth);
    }

    private function getTsxConfig(?TransactionConfiguration $config): TransactionConfiguration
    {
        if ($config !== null) {
            return $this->defaultTransactionConfiguration->merge($config);
        }

        return $this->defaultTransactionConfiguration;
    }
}
