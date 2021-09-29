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
use Laudis\Neo4j\Types\CypherMap;
use function sprintf;

/**
 * @template T
 *
 * @implements ClientInterface<T>
 */
final class Client implements ClientInterface
{
    private const DEFAULT_DRIVER_CONFIG = 'bolt://localhost:7687';

    /** @var CypherMap<DriverSetup> */
    private CypherMap $driverSetups;
    /** @var array<string, DriverInterface<T>> */
    private array $drivers;
    /** @var FormatterInterface<T> */
    private FormatterInterface $formatter;
    private DriverConfiguration $configuration;
    private ?string $default;

    /**
     * @param CypherMap<DriverSetup> $driverConfigurations
     * @param FormatterInterface<T>  $formatter
     */
    public function __construct(CypherMap $driverConfigurations, DriverConfiguration $configuration, FormatterInterface $formatter, ?string $default)
    {
        $this->driverSetups = $driverConfigurations;
        $this->drivers = [];
        $this->formatter = $formatter;
        $this->configuration = $configuration;
        $this->default = $default;
    }

    public function run(string $query, iterable $parameters = [], ?string $alias = null)
    {
        return $this->startSession($alias, SessionConfiguration::default())->run($query, $parameters);
    }

    public function runStatement(Statement $statement, ?string $alias = null)
    {
        return $this->startSession($alias, SessionConfiguration::default())->runStatement($statement);
    }

    public function runStatements(iterable $statements, ?string $alias = null): CypherList
    {
        return $this->startSession($alias, SessionConfiguration::default())->runStatements($statements);
    }

    public function beginTransaction(?iterable $statements = null, ?string $alias = null, ?TransactionConfiguration $config = null): UnmanagedTransactionInterface
    {
        return $this->startSession($alias, SessionConfiguration::default())->beginTransaction($statements, $config);
    }

    public function getDriver(?string $alias): DriverInterface
    {
        $this->createDefaultDriverIfNeeded();

        $alias = $this->decideAlias($alias);

        if (!isset($this->drivers[$alias])) {
            if (!$this->driverSetups->hasKey($alias)) {
                $key = sprintf('The provided alias: "%s" was not found in the connection pool', $alias);
                throw new InvalidArgumentException($key);
            }

            $setup = $this->driverSetups->get($alias);
            $uri = $setup->getUri();
            $timeout = $setup->getSocketTimeout();
            $driver = DriverFactory::create($uri, $this->configuration, $setup->getAuth(), $timeout, $this->formatter);

            $this->drivers[$alias] = $driver;
        }

        return $this->drivers[$alias];
    }

    /**
     * @return SessionInterface<T>
     */
    private function startSession(?string $alias = null, SessionConfiguration $configuration = null): SessionInterface
    {
        return $this->getDriver($alias)->createSession($configuration ?? SessionConfiguration::default());
    }

    public function writeTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        return $this->startSession($alias, SessionConfiguration::default()->withAccessMode(AccessMode::WRITE()))->writeTransaction($tsxHandler, $config);
    }

    public function readTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        return $this->startSession($alias, SessionConfiguration::default()->withAccessMode(AccessMode::READ()))->readTransaction($tsxHandler, $config);
    }

    public function transaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        return $this->writeTransaction($tsxHandler, $alias, $config);
    }

    private function createDefaultDriverIfNeeded(): void
    {
        if ($this->driverSetups->isEmpty() && count($this->drivers) === 0) {
            $driver = DriverFactory::create(self::DEFAULT_DRIVER_CONFIG, null, null, null, $this->formatter);
            $this->drivers['default'] = $driver;
        }
    }

    private function decideAlias(?string $alias): string
    {
        if ($alias !== null) {
            return $alias;
        }

        if ($this->default !== null) {
            return $this->default;
        }

        if ($this->driverSetups->count() > 0) {
            return $this->driverSetups->first()->getKey();
        }

        return 'default';
    }
}
