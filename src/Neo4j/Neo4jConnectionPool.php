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
            $this->getLogger()?->log(LogLevel::DEBUG, 'Routing table not found in cache, fetching new routing table');

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

                    $this->getLogger()?->log(LogLevel::DEBUG, 'Successfully fetched routing table', [
                        'ttl' => $table->getTtl(),
                        'leaders' => $table->getWithRole(RoutingRoles::LEADER()),
                        'followers' => $table->getWithRole(RoutingRoles::FOLLOWER()),
                        'routers' => $table->getWithRole(RoutingRoles::ROUTE()),
                    ]);
                } catch (ConnectException $e) {
                    $this->getLogger()?->log(LogLevel::WARNING, 'Failed to connect to address', [
                        'address' => $address,
                        'error' => $e->getMessage(),
                    ]);
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

        $this->getLogger()?->log(LogLevel::DEBUG, 'Acquiring connection from server', [
            'server' => (string) $server,
            'access_mode' => $config->getAccessMode()?->getValue(),
        ]);

        return $this->createOrGetPool($this->data->getUri()->getHost(), $server)->acquire($config);
    }

    public function getLogger(): ?Neo4jLogger
    {
        return $this->logger;
    }

    /**
     * Get the current routing table from cache for the given session configuration.
     *
     * @return RoutingTable|null The cached routing table, or null if not yet initialized
     */
    public function getRoutingTable(SessionConfiguration $config): ?RoutingTable
    {
        $key = $this->createKey($this->data, $config);
        /** @var RoutingTable|null $table */
        $table = $this->cache->get($key);

        return $table;
    }

    /**
     * Clear the cached routing table for the given session configuration.
     * This forces a new routing table to be fetched on the next acquire() call.
     */
    public function clearRoutingTable(SessionConfiguration $config): void
    {
        $key = $this->createKey($this->data, $config);
        $deleted = $this->cache->delete($key);

        $this->getLogger()?->log(LogLevel::INFO, 'Cleared routing table from cache', [
            'key' => $key,
            'deleted' => $deleted,
        ]);
    }

    /**
     * Remove a failed server from the routing table.
     * This removes the server from all roles (leader, follower, router) and updates the cache.
     *
     * @param SessionConfiguration $config        The session configuration
     * @param string               $serverAddress The address of the failed server (e.g., "172.18.0.3:9010")
     */
    public function removeFailedServer(SessionConfiguration $config, string $serverAddress): void
    {
        $key = $this->createKey($this->data, $config);
        /** @var RoutingTable|null $table */
        $table = $this->cache->get($key);

        if ($table !== null) {
            $this->getLogger()?->log(LogLevel::WARNING, 'Removing failed server from routing table', [
                'server' => $serverAddress,
            ]);

            // Remove the server and update cache
            $updatedTable = $table->removeServer($serverAddress);

            // Only update cache if the table actually changed
            if ($updatedTable !== $table) {
                $this->cache->set($key, $updatedTable, $updatedTable->getTtl());

                $this->getLogger()?->log(LogLevel::INFO, 'Updated routing table after removing failed server', [
                    'server' => $serverAddress,
                    'remaining_leaders' => $updatedTable->getWithRole(RoutingRoles::LEADER()),
                    'remaining_followers' => $updatedTable->getWithRole(RoutingRoles::FOLLOWER()),
                    'remaining_routers' => $updatedTable->getWithRole(RoutingRoles::ROUTE()),
                ]);
            }
        }
    }

    /**
     * Check if a server exists in the routing table.
     *
     * @param SessionConfiguration $config        The session configuration
     * @param string               $serverAddress The address of the server to check
     *
     * @return bool True if the server exists in the routing table, false otherwise
     */
    public function hasServer(SessionConfiguration $config, string $serverAddress): bool
    {
        $table = $this->getRoutingTable($config);

        if ($table === null) {
            return false;
        }

        return $table->hasServer($serverAddress);
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

        if (count($servers) === 0) {
            throw new RuntimeException(sprintf('No servers available for access mode: %s', $mode?->getValue() ?? 'WRITE'));
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

        return new RoutingTable($servers, $ttl);
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
        $this->getLogger()?->log(LogLevel::INFO, 'Closing all connection pools');

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
