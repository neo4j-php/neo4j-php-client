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
use Laudis\Neo4j\Contracts\TransactionInterface;
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
     * @var array<string, SessionInterface<ResultFormat>>
     */
    private array $boundSessions = [];

    /**
     * @psalm-mutation-free
     *
     * @param DriverSetupManager<ResultFormat> $driverSetups
     */
    public function __construct(
        private readonly DriverSetupManager $driverSetups,
        private readonly SessionConfiguration $defaultSessionConfiguration,
        private readonly TransactionConfiguration $defaultTransactionConfiguration
    ) {}

    public function getDriverSetups(): DriverSetupManager
    {
        return $this->driverSetups;
    }

    public function getDefaultSessionConfiguration(): SessionConfiguration
    {
        return $this->defaultSessionConfiguration;
    }

    public function getDefaultTransactionConfiguration(): TransactionConfiguration
    {
        return $this->defaultTransactionConfiguration;
    }

    public function run(string $statement, iterable $parameters = [], ?string $alias = null)
    {
        return $this->runStatement(Statement::create($statement, $parameters), $alias);
    }

    public function runStatement(Statement $statement, ?string $alias = null)
    {
        return $this->runStatements([$statement], $alias)->first();
    }

    private function getRunner(?string $alias = null): TransactionInterface|SessionInterface
    {
        $alias ??= $this->driverSetups->getDefaultAlias();

        if (array_key_exists($alias, $this->boundTransactions) &&
            count($this->boundTransactions[$alias]) > 0) {
            return $this->boundTransactions[$alias][array_key_last($this->boundTransactions[$alias])];
        }

        return $this->getSession($alias);
    }

    private function getSession(?string $alias = null): SessionInterface
    {
        $alias ??= $this->driverSetups->getDefaultAlias();

        if (array_key_exists($alias, $this->boundSessions)) {
            return $this->boundSessions[$alias];
        }

        return $this->boundSessions[$alias] = $this->startSession($alias, $this->defaultSessionConfiguration);
    }

    public function runStatements(iterable $statements, ?string $alias = null): CypherList
    {
        $runner = $this->getRunner($alias);
        if ($runner instanceof SessionInterface) {
            return $runner->runStatements($statements, $this->defaultTransactionConfiguration);
        }

        return $runner->runStatements($statements);
    }

    public function beginTransaction(?iterable $statements = null, ?string $alias = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        $session = $this->getSession($alias);
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
        if ($this->defaultSessionConfiguration->getAccessMode() === AccessMode::WRITE()) {
            $session = $this->getSession($alias);
        } else {
            $sessionConfig = $this->defaultSessionConfiguration->withAccessMode(AccessMode::WRITE());
            $session = $this->startSession($alias, $sessionConfig);
        }

        return $session->writeTransaction($tsxHandler, $this->getTsxConfig($config));
    }

    public function readTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        if ($this->defaultSessionConfiguration->getAccessMode() === AccessMode::READ()) {
            $session = $this->getSession($alias);
        } else {
            $sessionConfig = $this->defaultSessionConfiguration->withAccessMode(AccessMode::WRITE());
            $session = $this->startSession($alias, $sessionConfig);
        }

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

    public function hasDriver(string $alias): bool
    {
        return $this->driverSetups->hasDriver($alias);
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
