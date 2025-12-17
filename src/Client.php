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

use Bolt\error\ConnectException;
use Laudis\Neo4j\Common\DriverSetupManager;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\ConnectionPoolException;
use Laudis\Neo4j\Types\CypherList;

/**
 * A collection of drivers with methods to run queries though them.
 */
final class Client implements ClientInterface
{
    /**
     * @var array<string, list<UnmanagedTransactionInterface>>
     */
    private array $boundTransactions = [];

    /**
     * @var array<string, SessionInterface>
     */
    private array $boundSessions = [];

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly DriverSetupManager $driverSetups,
        private readonly SessionConfiguration $defaultSessionConfiguration,
        private readonly TransactionConfiguration $defaultTransactionConfiguration,
    ) {
    }

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

    public function run(string $statement, iterable $parameters = [], ?string $alias = null): SummarizedResult
    {
        return $this->runStatement(Statement::create($statement, $parameters), $alias);
    }

    public function runStatement(Statement $statement, ?string $alias = null): SummarizedResult
    {
        return $this->runStatements([$statement], $alias)->first();
    }

    private function getRunner(?string $alias = null): TransactionInterface|SessionInterface
    {
        $alias ??= $this->driverSetups->getDefaultAlias();

        if (array_key_exists($alias, $this->boundTransactions)
            && count($this->boundTransactions[$alias]) > 0) {
            /** @psalm-suppress PossiblyNullArrayOffset */
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

    /**
     * Executes an operation with automatic retry on alternative drivers when connection exceptions occur.
     *
     * @template T
     *
     * @param callable(SessionInterface): T $operation The operation to execute
     * @param string|null                   $alias     The driver alias to use
     *
     * @throws ConnectionPoolException When all available drivers have been exhausted
     *
     * @return T The result of the operation
     */
    private function executeWithRetry(callable $operation, ?string $alias = null)
    {
        $alias ??= $this->driverSetups->getDefaultAlias();
        $attemptedDrivers = [];
        $lastException = null;

        while (true) {
            try {
                $driver = $this->driverSetups->getDriver($this->defaultSessionConfiguration, $alias);

                $driverHash = spl_object_hash($driver);
                if (in_array($driverHash, $attemptedDrivers, true)) {
                    throw $lastException ?? new ConnectionPoolException('No available drivers');
                }
                $attemptedDrivers[] = $driverHash;

                $session = $driver->createSession($this->defaultSessionConfiguration);

                return $operation($session);
            } catch (ConnectionPoolException|ConnectException $e) {
                $lastException = $e;
            }
        }
    }

    public function runStatements(iterable $statements, ?string $alias = null): CypherList
    {
        $alias ??= $this->driverSetups->getDefaultAlias();

        if (array_key_exists($alias, $this->boundTransactions)
            && count($this->boundTransactions[$alias]) > 0) {
            $runner = $this->getRunner($alias);
            if ($runner instanceof TransactionInterface) {
                return $runner->runStatements($statements);
            }
        }

        if (array_key_exists($alias, $this->boundSessions)) {
            $session = $this->boundSessions[$alias];

            return $session->runStatements($statements, $this->defaultTransactionConfiguration);
        }

        return $this->executeWithRetry(
            function (SessionInterface $session) use ($statements) {
                return $session->runStatements($statements, $this->defaultTransactionConfiguration);
            },
            $alias
        );
    }

    public function beginTransaction(?iterable $statements = null, ?string $alias = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        $alias ??= $this->driverSetups->getDefaultAlias();
        $config = $this->getTsxConfig($config);

        if (array_key_exists($alias, $this->boundSessions)) {
            return $this->boundSessions[$alias]->beginTransaction($statements, $config);
        }

        return $this->executeWithRetry(
            function (SessionInterface $session) use ($statements, $config) {
                return $session->beginTransaction($statements, $config);
            },
            $alias
        );
    }

    public function getDriver(?string $alias): DriverInterface
    {
        return $this->driverSetups->getDriver($this->defaultSessionConfiguration, $alias);
    }

    private function startSession(?string $alias, SessionConfiguration $configuration): SessionInterface
    {
        return $this->getDriver($alias)->createSession($configuration);
    }

    public function writeTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        $alias ??= $this->driverSetups->getDefaultAlias();
        $config = $this->getTsxConfig($config);

        if (array_key_exists($alias, $this->boundSessions)) {
            return $this->boundSessions[$alias]->writeTransaction($tsxHandler, $config);
        }

        return $this->executeWithRetry(
            function (SessionInterface $session) use ($tsxHandler, $config) {
                return $session->writeTransaction($tsxHandler, $config);
            },
            $alias
        );
    }

    public function readTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        $alias ??= $this->driverSetups->getDefaultAlias();
        $config = $this->getTsxConfig($config);

        if (array_key_exists($alias, $this->boundSessions)) {
            return $this->boundSessions[$alias]->readTransaction($tsxHandler, $config);
        }

        return $this->executeWithRetry(
            function (SessionInterface $session) use ($tsxHandler, $config) {
                return $session->readTransaction($tsxHandler, $config);
            },
            $alias
        );
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
     * @param callable(UnmanagedTransactionInterface): void $handler
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
