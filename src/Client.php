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

use function array_key_exists;
use InvalidArgumentException;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\DriverSetup;
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
    private const DEFAULT_DRIVER_CONFIG = 'bolt://localhost:7687';
    /** @var non-empty-array<string, DriverInterface<ResultFormat>> */
    private array $drivers;
    /** @psalm-readonly */
    private ?string $default;
    private SessionConfiguration $defaultSessionConfiguration;
    private TransactionConfiguration $defaultTransactionConfiguration;

    /**
     * @psalm-mutation-free
     *
     * @param array<string, DriverSetup>       $driverSetups
     * @param FormatterInterface<ResultFormat> $formatter
     */
    public function __construct(array $driverSetups, DriverConfiguration $defaultDriverConfiguration, SessionConfiguration $defaultSessionConfiguration, TransactionConfiguration $defaultTransactionConfiguration, FormatterInterface $formatter, ?string $default)
    {
        $this->default = $default;
        $this->drivers = $this->createDrivers($driverSetups, $formatter, $defaultDriverConfiguration);
        $this->defaultSessionConfiguration = $defaultSessionConfiguration;
        $this->defaultTransactionConfiguration = $defaultTransactionConfiguration;
    }

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

    /**
     * @psalm-mutation-free
     */
    public function getDriver(?string $alias): DriverInterface
    {
        $alias = $this->decideAlias($alias);

        if (!array_key_exists($alias, $this->drivers)) {
            throw new InvalidArgumentException(sprintf('The provided alias: "%s" was not found in the client', $alias));
        }

        return $this->drivers[$alias];
    }

    /**
     * @psalm-mutation-free
     *
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
        return $this->getDriver($driver)->verifyConnectivity();
    }

    /**
     * @psalm-mutation-free
     */
    private function decideAlias(?string $alias): string
    {
        return $alias ?? $this->default ?? array_key_first($this->drivers);
    }

    /**
     * @psalm-mutation-free
     *
     * @param array<string, DriverSetup>       $driverSetups
     * @param FormatterInterface<ResultFormat> $formatter
     *
     * @return non-empty-array<string, DriverInterface<ResultFormat>>
     */
    private function createDrivers(array $driverSetups, FormatterInterface $formatter, DriverConfiguration $configuration): array
    {
        if (count($driverSetups) === 0) {
            $drivers = ['default' => DriverFactory::create(self::DEFAULT_DRIVER_CONFIG, null, null, $formatter)];
        } else {
            $drivers = [];
            foreach ($driverSetups as $alias => $setup) {
                $uri = $setup->getUri();
                $auth = $setup->getAuth();

                $drivers[$alias] = DriverFactory::create($uri, $configuration, $auth, $formatter);
            }
        }

        /** @var non-empty-array<string, DriverInterface<ResultFormat>> */
        return $drivers;
    }

    private function getTsxConfig(?TransactionConfiguration $config): TransactionConfiguration
    {
        if ($config !== null) {
            return $this->defaultTransactionConfiguration->merge($config);
        }

        return $this->defaultTransactionConfiguration;
    }
}
