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
use function in_array;
use InvalidArgumentException;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\ClientConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\UnsupportedScheme;
use Laudis\Neo4j\Formatter\BasicFormatter;
use Laudis\Neo4j\Network\Bolt\BoltDriver;
use Laudis\Neo4j\Network\Http\HttpDriver;
use function sprintf;
use function var_export;

/**
 * @template T
 *
 * @implements ClientInterface<T>
 *
 * @psalm-import-type ParsedUrl from \Laudis\Neo4j\Network\Bolt\BoltDriver
 */
final class Client implements ClientInterface
{
    public const SUPPORTED_SCHEMES = ['bolt', 'bolt+s', 'bolt+ssc', 'neo4j', 'neo4j+s', 'neo4j+ssc', 'http', 'https'];

    /** @var Map<string, array{0: ParsedUrl, 1:AuthenticateInterface}> */
    private Map $driverConfigurations;
    /** @var Map<string, DriverInterface> */
    private Map $drivers;
    /** @var ClientConfiguration<T> */
    private ClientConfiguration $configuration;

    /**
     * @param ClientConfiguration<T>                                    $configuration
     * @param Map<string, array{0: ParsedUrl, 1:AuthenticateInterface}> $driverConfigurations
     */
    public function __construct(Map $driverConfigurations, ClientConfiguration $configuration)
    {
        $this->configuration = $configuration;
        $this->driverConfigurations = $driverConfigurations;
        $this->drivers = new Map();
    }

    /**
     * @return Client<Vector<Map<string, array|scalar|null>>>
     */
    public static function make(): Client
    {
        return new self(new Map(), ClientConfiguration::default());
    }

    public function run(string $query, iterable $parameters = [], ?string $alias = null)
    {
        return $this->startSession($alias)->run($query, $parameters);
    }

    public function runStatement(Statement $statement, ?string $alias = null)
    {
        return $this->startSession($alias)->runStatement($statement);
    }

    public function runStatements(iterable $statements, ?string $alias = null): Vector
    {
        return $this->startSession($alias)->runStatements($statements);
    }

    public function openTransaction(?iterable $statements = null, ?string $alias = null): UnmanagedTransactionInterface
    {
        return $this->startSession($alias)->beginTransaction($statements);
    }

    public function getDriver(?string $alias): DriverInterface
    {
        if ($this->driverConfigurations->count() === 0) {
            return $this->withDriver('default', '', Authenticate::disabled())->getDriver('default');
        }

        $alias ??= $this->configuration->getDefaultDriver() ?? $this->driverConfigurations->first()->key;
        if (!$this->driverConfigurations->hasKey($alias)) {
            $key = sprintf('The provided alias: "%s" was not found in the connection pool', $alias);
            throw new InvalidArgumentException($key);
        }

        [$parsedUrl, $authentication] = $this->driverConfigurations->get($alias);
        $scheme = $parsedUrl['scheme'] ?? 'bolt';

        if (in_array($scheme, ['bolt', 'bolt+s', 'bolt+ssc'])) {
            return $this->cacheDriver($alias, function () use ($parsedUrl, $authentication) {
                return $this->makeBoltDriver($parsedUrl, $authentication);
            });
        }

        if (in_array($scheme, ['neo4j', 'neo4j+s', 'neo4j+ssc'])) {
            return $this->cacheDriver($alias, function () use ($parsedUrl, $authentication) {
                return $this->makeNeo4jDriver($parsedUrl, $authentication);
            });
        }

        return $this->cacheDriver($alias, function () use ($parsedUrl, $authentication) {
            return $this->makeHttpDriver($parsedUrl, $authentication);
        });
    }

    public function startSession(?string $alias = null, ?SessionConfiguration $config = null): SessionInterface
    {
        return $this->getDriver($alias)->createSession($config)->withFormatter($this->configuration->getFormatter());
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

    public function withTransactionTimeout($timeout): Client
    {
        $config = $this->configuration->withTransactionTimeout($timeout);

        return new self($this->driverConfigurations, $config);
    }

    public function withFetchSize($fetchSize): Client
    {
        $config = $this->configuration->withFetchSize($fetchSize);

        return new self($this->driverConfigurations, $config);
    }

    public function withDefaultDriver($defaultDriver): Client
    {
        $config = $this->configuration->withDefaultDriver($defaultDriver);

        return new self($this->driverConfigurations, $config);
    }

    public function withAccessMode($accessMode): Client
    {
        $config = $this->configuration->withAccessMode($accessMode);

        return new self($this->driverConfigurations, $config);
    }

    public function withFormatter($formatter): Client
    {
        $config = $this->configuration->withFormatter($formatter);

        return new self($this->driverConfigurations, $config);
    }

    /**
     * @param ParsedUrl $parsedUrl
     *
     * @return HttpDriver<T>
     */
    private function makeHttpDriver(array $parsedUrl, AuthenticateInterface $authenticate): HttpDriver
    {
        $bindings = $this->configuration->getHttpPsrBindings();

        $driverConfig = $this->configuration->getDriverConfiguration();

        return new HttpDriver($parsedUrl, $bindings, $driverConfig, $authenticate, new ConnectionManager($bindings));
    }

    /**
     * @param ParsedUrl $parsedUrl
     *
     * @return BoltDriver<T>
     */
    private function makeBoltDriver(array $parsedUrl, AuthenticateInterface $authenticate): BoltDriver
    {
        $driverConfig = $this->configuration->getDriverConfiguration();
        $manager = new ConnectionManager($this->configuration->getDriverConfiguration()->getHttpPsrBindings());

        return new BoltDriver($parsedUrl, $authenticate, $manager, $driverConfig);
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
     * @return Neo4jDriver<T>
     */
    private function makeNeo4jDriver(array $parsedUrl, AuthenticateInterface $authenticate): Neo4jDriver
    {
        $driverConfig = $this->configuration->getDriverConfiguration();

        $manager = new ConnectionManager($this->configuration->getDriverConfiguration()->getHttpPsrBindings());
        $baseDriver = new BoltDriver($parsedUrl, $authenticate, $manager, $driverConfig->withFormatter(new BasicFormatter()));

        return new Neo4jDriver($parsedUrl, $authenticate, $baseDriver, $driverConfig);
    }

    /**
     * @return self<T>
     */
    public function withDriver(string $alias, string $url, ?AuthenticateInterface $authentication = null): Client
    {
        return $this->addParsedUrl($alias, ConnectionManager::parseUrl($url), $authentication);
    }

    /**
     * @param ParsedUrl $parsedUrl
     *
     * @return self<T>
     */
    private function addParsedUrl(string $alias, array $parsedUrl, ?AuthenticateInterface $authentication): Client
    {
        $scheme = $parsedUrl['scheme'] ?? 'bolt';
        $authentication ??= Authenticate::fromUrl();

        if (!in_array($scheme, self::SUPPORTED_SCHEMES, true)) {
            throw UnsupportedScheme::make($scheme, self::SUPPORTED_SCHEMES);
        }

        $configs = $this->driverConfigurations->copy();
        $configs->put($alias, [$parsedUrl, $authentication]);

        return new self($configs, $this->configuration);
    }

    public function withHttpPsrBindings($bindings): Client
    {
        return new self($this->driverConfigurations, $this->configuration->withHttpPsrBindings($bindings));
    }

    public function withUserAgent($userAgent): Client
    {
        return new self($this->driverConfigurations, $this->configuration->withUserAgent($userAgent));
    }

    /**
     * @param ParsedUrl $parsedUrl
     *
     * @return self<T>
     */
    public function withParsedUrl(string $alias, array $parsedUrl, AuthenticateInterface $auth): self
    {
        return $this->addParsedUrl($alias, $parsedUrl, $auth);
    }
}
