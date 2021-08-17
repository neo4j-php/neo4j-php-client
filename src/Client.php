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
use InvalidArgumentException;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
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
use function microtime;
use const PHP_EOL;
use Psr\Http\Message\UriInterface;
use function sprintf;

/**
 * @template T
 *
 * @implements ClientInterface<T>
 */
final class Client implements ClientInterface
{
    private const DEFAULT_DRIVER_CONFIG = 'bolt://localhost:7687';

    /** @var Map<string, DriverSetup|DriverInterface<T>> */
    private Map $driverConfigurations;
    /** @var Map<string, DriverInterface> */
    private Map $drivers;
    /** @var FormatterInterface<T> */
    private FormatterInterface $formatter;
    private DriverConfiguration $configuration;
    private ?string $default;

    /**
     * @param Map<string, DriverSetup> $driverConfigurations
     * @param FormatterInterface<T>    $formatter
     */
    public function __construct(Map $driverConfigurations, DriverConfiguration $configuration, FormatterInterface $formatter, ?string $default)
    {
        $this->driverConfigurations = new Map();
        foreach ($driverConfigurations as $key => $value) {
            $this->driverConfigurations->put($key, $value);
        }
        $this->drivers = new Map();
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
        if ($this->driverConfigurations->count() === 0) {
            $driver = $this->makeDriver(Uri::create('bolt://localhost:7687'), 'default', Authenticate::disabled(), TransactionConfiguration::DEFAULT_TIMEOUT);
            $this->driverConfigurations->put('default', $driver);
        }

        $alias ??= $this->default ?? $this->driverConfigurations->first()->key;
        if (!$this->driverConfigurations->hasKey($alias)) {
            $key = sprintf('The provided alias: "%s" was not found in the connection pool', $alias);
            throw new InvalidArgumentException($key);
        }

        $driverOrSetup = $this->driverConfigurations->get($alias);
        if ($driverOrSetup instanceof DriverSetup) {
            $driverOrSetup = $this->makeDriver($driverOrSetup->getUri(), $alias, $driverOrSetup->getAuth(), $driverOrSetup->getSocketTimeout());
            $this->driverConfigurations->put($alias, $driverOrSetup);
        }

        return $driverOrSetup;
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

    /**
     * @template U as DriverInterface
     *
     * @param callable():U $factory
     *
     * @return U
     */
    private function cacheDriver(string $alias, callable $factory): DriverInterface
    {
        /** @var U|null */
        $tbr = $this->drivers->get($alias, null);
        $tbr ??= $factory();

        $this->drivers->put($alias, $tbr);

        return $tbr;
    }

    /**
     * @param ParsedUrl $parsedUrl
     *
     * @return DriverInterface<T>
     */
    private function makeDriver(UriInterface $uri, string $alias, AuthenticateInterface $authentication, float $socketTimeout): DriverInterface
    {
        return $this->cacheDriver($alias, function () use ($uri, $authentication, $socketTimeout) {
            return DriverFactory::create($uri, $this->configuration, $authentication, $socketTimeout, $this->formatter);
        });
    }
}
