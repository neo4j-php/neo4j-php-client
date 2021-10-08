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

    /**
     * @psalm-mutation-free
     *
     * @param CypherMap<DriverSetup>           $driverSetups
     * @param FormatterInterface<ResultFormat> $formatter
     */
    public function __construct(CypherMap $driverSetups, DriverConfiguration $configuration, FormatterInterface $formatter, ?string $default)
    {
        $this->default = $default;
        $this->drivers = $this->createDrivers($driverSetups, $formatter, $configuration);
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

    /**
     * @psalm-mutation-free
     */
    public function getDriver(?string $alias): DriverInterface
    {
        $alias = $this->decideAlias($alias);

        return $this->drivers[$alias];
    }

    /**
     * @psalm-mutation-free
     *
     * @return SessionInterface<ResultFormat>
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

    /**
     * @psalm-mutation-free
     */
    private function decideAlias(?string $alias): string
    {
        if ($alias !== null) {
            return $alias;
        }

        if ($this->default !== null) {
            return $this->default;
        }

        return array_key_first($this->drivers);
    }

    /**
     * @psalm-mutation-free
     *
     * @param CypherMap<DriverSetup>           $driverSetups
     * @param FormatterInterface<ResultFormat> $formatter
     *
     * @return non-empty-array<string, DriverInterface<ResultFormat>>
     */
    private function createDrivers(CypherMap $driverSetups, FormatterInterface $formatter, DriverConfiguration $configuration): array
    {
        if (count($driverSetups) === 0) {
            $drivers = ['default' => DriverFactory::create(self::DEFAULT_DRIVER_CONFIG, null, null, null, $formatter)];
        } else {
            $drivers = [];
            foreach ($driverSetups as $alias => $setup) {
                $uri = $setup->getUri();
                $timeout = $setup->getSocketTimeout();
                $auth = $setup->getAuth();

                $drivers[$alias] = DriverFactory::create($uri, $configuration, $auth, $timeout, $formatter);
            }
        }

        /** @var non-empty-array<string, DriverInterface<ResultFormat>> */
        return $drivers;
    }
}
