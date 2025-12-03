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

namespace Laudis\Neo4j\Neo4j;

use Bolt\error\ConnectException;

use function count;

use Exception;
use Generator;

use function implode;

use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\ConnectionPool;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\Cache;
use Laudis\Neo4j\Common\GeneratorHelper;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AddressResolverInterface;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SemaphoreInterface;
use Laudis\Neo4j\Databags\ConnectionRequestData;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\RoutingRoles;
use Psr\Http\Message\UriInterface;
use Psr\Log\LogLevel;
use Psr\SimpleCache\CacheInterface;

use function random_int;

use RuntimeException;

use function sprintf;
use function str_replace;
use function time;

/**
 * Connection pool for with auto client-side routing.
 *
 * @psalm-import-type BasicDriver from DriverInterface
 *
 * @implements ConnectionPoolInterface<BoltConnection>
 */
final class Neo4jConnectionPool implements ConnectionPoolInterface
{
    /** @var array<string, ConnectionPool> */
    private static array $pools = [];

    /**
     * Registry of routing tables per database.
     * Maps database name -> RoutingTable.
     *
     * @var array<string, RoutingTable>
     */
    private array $routingTableRegistry = [];

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly SemaphoreInterface $semaphore,
        private readonly BoltFactory $factory,
        private readonly ConnectionRequestData $data,
        private readonly CacheInterface $cache,
        private readonly AddressResolverInterface $resolver,
        private readonly ?Neo4jLogger $logger,
        private readonly float $acquireConnectionTimeout,
    ) {
    }

    public static function create(
        UriInterface $uri,
        AuthenticateInterface $auth,
        DriverConfiguration $conf,
        AddressResolverInterface $resolver,
        SemaphoreInterface $semaphore,
    ): self {
        return new self(
            $semaphore,
            BoltFactory::create($conf->getLogger()),
            new ConnectionRequestData(
                $uri->getHost(),
                $uri,
                $auth,
                $conf->getUserAgent(),
                $conf->getSslConfiguration()
            ),
            Cache::getInstance(),
            $resolver,
            $conf->getLogger(),
            $conf->getAcquireConnectionTimeout()
        );
    }

    public function createOrGetPool(string $hostname, UriInterface $uri): ConnectionPool
    {
        $data = new ConnectionRequestData(
            $hostname,
            $uri,
            $this->data->getAuth(),
            $this->data->getUserAgent(),
            $this->data->getSslConfig()
        );

        $key = $this->createKey($data);
        if (!array_key_exists($key, self::$pools)) {
            self::$pools[$key] = new ConnectionPool($this->semaphore, $this->factory, $data, $this->logger, $this->acquireConnectionTimeout);
        }

        return self::$pools[$key];
    }

    /**
     * @throws Exception
     */
    public function acquire(SessionConfiguration $config): Generator
    {
        $key = $this->createKey($this->data, $config);

        /** @var RoutingTable|null $table */
        $table = $this->cache->get($key);
        $triedAddresses = [];

        $latestError = null;

        if ($table == null) {
            $addresses = $this->getAddresses($this->data->getUri()->getHost());
            foreach ($addresses as $address) {
                $triedAddresses[] = $address;

                $pool = $this->createOrGetPool(
                    $this->data->getUri()->getHost(),
                    $this->data->getUri()->withHost($address)
                );
                try {
                    /**
                     * @var BoltConnection $connection
                     *
                     * @psalm-suppress UnnecessaryVarAnnotation
                     */
                    $connection = GeneratorHelper::getReturnFromGenerator($pool->acquire($config));
                    $table = $this->routingTable($connection, $config);
                } catch (ConnectException $e) {
                    // todo - once client side logging is implemented it must be conveyed here.
                    $latestError = $e;
                    continue; // We continue if something is wrong with the current server
                }

                $this->cache->set($key, $table, $table->getTtl());
                // TODO: release probably logs off the connection, it is not preferable
                $pool->release($connection);

                break;
            }
        }

        if ($table === null) {
            throw new RuntimeException(sprintf('Cannot connect to host: "%s". Hosts tried: "%s"', $this->data->getUri()->getHost(), implode('", "', $triedAddresses)), previous: $latestError);
        }

        $server = $this->getNextServer($table, $config->getAccessMode());

        if ($server->getScheme() === '') {
            $server = $server->withScheme($this->data->getUri()->getScheme());
        }

        return $this->createOrGetPool($this->data->getUri()->getHost(), $server)->acquire($config);
    }

    public function getLogger(): ?Neo4jLogger
    {
        return $this->logger;
    }

    /**
     * Returns the routing table for a specific database, or null if not yet initialized.
     * This method is intended for testkit backend access to routing table information.
     *
     * @param string $database The database name to retrieve routing table for
     *
     * @return RoutingTable|null The routing table if available, null otherwise
     */
    public function getRoutingTable(string $database = 'neo4j'): ?RoutingTable
    {
        return $this->routingTableRegistry[$database] ?? null;
    }

    /**
     * Returns the complete routing table registry for all databases.
     * This method is intended for testkit backend access to routing information.
     *
     * @return array<string, RoutingTable> Map of database name to RoutingTable
     */
    public function getRoutingTableRegistry(): array
    {
        return $this->routingTableRegistry;
    }

    /**
     * Clears the routing table registry for a specific database or all databases.
     * This is used to force a routing table refresh on the next session.
     *
     * @param string|null $database Database to clear, or null to clear all
     */
    public function clearRoutingTable(?string $database = null): void
    {
        if ($database === null) {
            $this->routingTableRegistry = [];
            // Also clear the entire cache to force routing table refresh
            $this->cache->clear();
        } else {
            unset($this->routingTableRegistry[$database]);
            // Also clear the specific cache key for this database
            $key = $this->createKey($this->data, new SessionConfiguration(database: $database));
            $this->cache->delete($key);
        }
    }

    /**
     * @throws Exception
     */
    private function getNextServer(RoutingTable $table, ?AccessMode $mode): Uri
    {
        if ($mode === null || AccessMode::WRITE() === $mode) {
            $servers = $table->getWithRole(RoutingRoles::LEADER());
        } else {
            $servers = $table->getWithRole(RoutingRoles::FOLLOWER());
        }

        return Uri::create($servers[random_int(0, count($servers) - 1)]);
    }

    /**
     * @throws Exception
     */
    private function routingTable(BoltConnection $connection, SessionConfiguration $config): RoutingTable
    {
        $bolt = $connection->protocol();

        $this->getLogger()?->log(LogLevel::DEBUG, 'ROUTE', ['db' => $config->getDatabase()]);
        /** @var array{rt: array{servers: list<array{addresses: list<string>, role:string}>, ttl: int}} $route */
        $route = $bolt->route([], [], ['db' => $config->getDatabase()])
            ->getResponse()
            ->content;

        ['servers' => $servers, 'ttl' => $ttl] = $route['rt'];
        $ttl += time();

        $table = new RoutingTable($servers, $ttl);

        // Store in routing table registry for testkit access
        $database = $config->getDatabase() ?? 'neo4j';
        $this->routingTableRegistry[$database] = $table;

        return $table;
    }

    public function release(ConnectionInterface $connection): void
    {
        $this->createOrGetPool($connection->getServerAddress()->getHost(), $connection->getServerAddress())->release(
            $connection
        );
    }

    private function createKey(ConnectionRequestData $data, ?SessionConfiguration $config = null): string
    {
        $uri = $data->getUri();

        $key = implode(
            ':',
            array_filter(
                [
                    $data->getUserAgent(),
                    $uri->getHost(),
                    $config?->getDatabase(),
                    $uri->getPort() ?? '7687',
                ]
            )
        );

        return str_replace([
            '{',
            '}',
            '(',
            ')',
            '/',
            '\\',
            '@',
            ':',
        ], '|', $key);
    }

    public function close(): void
    {
        foreach (self::$pools as $pool) {
            $pool->close();
        }
        self::$pools = [];
        $this->cache->clear();
    }

    /**
     * @return Generator<string>
     */
    private function getAddresses(string $host): Generator
    {
        yield gethostbyname($host);
        yield from $this->resolver->getAddresses($host);
    }
}
