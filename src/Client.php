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
use function in_array;
use InvalidArgumentException;
use function is_array;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Bolt\BoltDriver;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Http\HttpDriver;
use Laudis\Neo4j\Neo4j\Neo4jDriver;
use Laudis\Neo4j\Types\CypherList;
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

    /** @var Map<string, array{0: Uri, 1:AuthenticateInterface}|DriverInterface<T>> */
    private Map $driverConfigurations;
    /** @var Map<string, DriverInterface> */
    private Map $drivers;
    /** @var FormatterInterface<T> */
    private FormatterInterface $formatter;
    private DriverConfiguration $configuration;
    private ?string $default;

    /**
     * @param Map<string, array{0: Uri, 1:AuthenticateInterface}> $driverConfigurations
     * @param FormatterInterface<T>                               $formatter
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
        return $this->startSession($alias)->run($query, $parameters);
    }

    public function runStatement(Statement $statement, ?string $alias = null)
    {
        return $this->startSession($alias)->runStatement($statement);
    }

    public function runStatements(iterable $statements, ?string $alias = null): CypherList
    {
        return $this->startSession($alias)->runStatements($statements);
    }

    public function beginTransaction(?iterable $statements = null, ?string $alias = null): UnmanagedTransactionInterface
    {
        return $this->startSession($alias)->beginTransaction($statements);
    }

    public function getDriver(?string $alias): DriverInterface
    {
        if ($this->driverConfigurations->count() === 0) {
            $driver = $this->makeDriver(Uri::create('bolt://localhost:7687'), 'default', Authenticate::disabled());
            $this->driverConfigurations->put('default', $driver);
        }

        $alias ??= $this->default ?? $this->driverConfigurations->first()->key;
        if (!$this->driverConfigurations->hasKey($alias)) {
            $key = sprintf('The provided alias: "%s" was not found in the connection pool', $alias);
            throw new InvalidArgumentException($key);
        }

        $driverOrConfig = $this->driverConfigurations->get($alias);
        if (is_array($driverOrConfig)) {
            [$parsedUrl, $authentication] = $driverOrConfig;
            $driverOrConfig = $this->makeDriver($parsedUrl, $alias, $authentication);
            $this->driverConfigurations->put($alias, $driverOrConfig);
        }

        return $driverOrConfig;
    }

    /**
     * @return SessionInterface<T>
     */
    private function startSession(?string $alias = null): SessionInterface
    {
        return $this->getDriver($alias)->createSession();
    }

    public function writeTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        return $this->startSession($alias)->writeTransaction($tsxHandler, $config);
    }

    public function readTransaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        return $this->startSession($alias)->readTransaction($tsxHandler, $config);
    }

    public function transaction(callable $tsxHandler, ?string $alias = null, ?TransactionConfiguration $config = null)
    {
        return $this->startSession($alias)->transaction($tsxHandler, $config);
    }

    /**
     * @param ParsedUrl $parsedUrl
     *
     * @return HttpDriver<T>
     */
    private function makeHttpDriver(Uri $uri, AuthenticateInterface $authenticate): HttpDriver
    {
        return HttpDriver::createWithFormatter($uri, $this->formatter, $this->configuration, $authenticate);
    }

    /**
     * @return BoltDriver<T>
     */
    private function makeBoltDriver(Uri $uri, AuthenticateInterface $authenticate): BoltDriver
    {
        return BoltDriver::createWithFormatter($uri, $this->formatter, $this->configuration, $authenticate);
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
     * @return Neo4jDriver<T>
     */
    private function makeNeo4jDriver(UriInterface $uri, AuthenticateInterface $authenticate): Neo4jDriver
    {
        return Neo4jDriver::createWithFormatter($uri, $this->formatter, $this->configuration, $authenticate);
    }

    /**
     * @param ParsedUrl $parsedUrl
     *
     * @return DriverInterface<T>
     */
    private function makeDriver(Uri $uri, string $alias, AuthenticateInterface $authentication): DriverInterface
    {
        $scheme = $uri->getScheme();
        $scheme = $scheme === '' ? 'bolt' : $scheme;

        if (in_array($scheme, ['bolt', 'bolt+s', 'bolt+ssc'])) {
            return $this->cacheDriver($alias, function () use ($uri, $authentication) {
                return $this->makeBoltDriver($uri, $authentication);
            });
        }

        if (in_array($scheme, ['neo4j', 'neo4j+s', 'neo4j+ssc'])) {
            return $this->cacheDriver($alias, function () use ($uri, $authentication) {
                return $this->makeNeo4jDriver($uri, $authentication);
            });
        }

        return $this->cacheDriver($alias, function () use ($uri, $authentication) {
            return $this->makeHttpDriver($uri, $authentication);
        });
    }
}
