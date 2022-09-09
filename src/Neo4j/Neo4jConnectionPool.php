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

use function array_slice;
use function array_unique;
use Bolt\error\MessageException;
use Bolt\protocol\V3;
use Bolt\protocol\V4;
use Bolt\protocol\V4_3;
use Bolt\protocol\V4_4;
use function count;
use Exception;
use Generator;
use Laudis\Neo4j\Bolt\Connection;
use Laudis\Neo4j\Bolt\ConnectionPool;
use Laudis\Neo4j\Common\GeneratorHelper;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\ConnectionFactoryInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SemaphoreInterface;
use Laudis\Neo4j\Databags\ConnectionRequestData;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\RoutingRoles;
use const PHP_INT_MAX;
use Psr\Http\Message\UriInterface;
use Psr\SimpleCache\CacheInterface;
use function random_int;
use function str_replace;
use function str_starts_with;
use function time;

/**
 * Connection pool for with auto client-side routing.
 *
 * @psalm-import-type BasicDriver from DriverInterface
 *
 * @implements ConnectionPoolInterface<array{0:V3, 1: Connection}>
 */
final class Neo4jConnectionPool implements ConnectionPoolInterface
{
    /** @var array<string, ConnectionPool<array{0: V3, 1: Connection}>> */
    private static array $pools = [];
    private SemaphoreInterface $semaphore;
    private ConnectionFactoryInterface $factory;
    private ConnectionRequestData $data;
    private CacheInterface $cache;

    /**
     * @psalm-mutation-free
     */
    public function __construct(SemaphoreInterface $semaphore, ConnectionFactoryInterface $factory, ConnectionRequestData $data, CacheInterface $cache)
    {
        $this->semaphore = $semaphore;
        $this->factory = $factory;
        $this->data = $data;
        $this->cache = $cache;
    }

    public function createOrGetPool(UriInterface $uri): ConnectionPool
    {
        $data = new ConnectionRequestData(
            $uri,
            $this->data->getAuth(),
            $this->data->getUserAgent(),
            $this->data->getSslConfig()
        );

        $key = $this->createKey($data);
        if (!array_key_exists($key, self::$pools)) {
            self::$pools[$key] = new ConnectionPool($this->semaphore, $this->factory, $data);
        }

        return self::$pools[$key];
    }

    /**
     * @throws Exception
     */
    public function acquire(SessionConfiguration $config): Generator
    {
        $key = $this->createKey($this->data);

        $routing = $this->cache->get($key, null);
        if ($routing == null) {
            $pool = $this->createOrGetPool($this->data);
            $connection = GeneratorHelper::getReturnFromGenerator($pool->acquire($config));
            $table = $this->routingTable($connection, $config);
            $this->cache->set($key, $table);
            $pool->release($connection);
        }

        $server = $this->getNextServer($table, $config->getAccessMode()) ?? $this->data->getUri();

        if ($server->getScheme() === '') {
            $server = $server->withScheme($this->data->getUri()->getScheme());
        }

        return $this->createOrGetPool($server)->acquire($config);
    }

    /**
     * @throws Exception
     */
    private function getNextServer(RoutingTable $table, AccessMode $mode): ?Uri
    {
        $servers = array_unique($table->getWithRole());
        if (count($servers) === 1) {
            return null;
        }

        if (AccessMode::WRITE() === $mode) {
            $servers = $table->getWithRole(RoutingRoles::LEADER());
        } else {
            $servers = $table->getWithRole(RoutingRoles::FOLLOWER());
        }

        return Uri::create($servers[random_int(0, count($servers) - 1)]);
    }

    /**
     * @param ConnectionInterface<V3> $connection
     *
     * @throws Exception
     */
    private function routingTable(ConnectionInterface $connection, SessionConfiguration $config): RoutingTable
    {
        $bolt = $connection->getImplementation();

        if ($bolt instanceof V4_4) {
            return $this->useRouteMessageNew($bolt, $config);
        }

        if ($bolt instanceof V4_3) {
            return $this->useRouteMessage($bolt, $config);
        }

        if ($bolt instanceof V4) {
            return $this->useRoutingTable($bolt);
        }

        return $this->useClusterOverview($bolt, $connection);
    }

    private function useRouteMessage(V4_3 $bolt, SessionConfiguration $config): RoutingTable
    {
        /** @var array{rt: array{servers: list<array{addresses: list<string>, role:string}>, ttl: int}} $route */
        $route = $bolt->route([], [], $config->getDatabase());
        ['servers' => $servers, 'ttl' => $ttl] = $route['rt'];
        $ttl += time();

        return new RoutingTable($servers, $ttl);
    }

    private function useRouteMessageNew(V4_4 $bolt, SessionConfiguration $config): RoutingTable
    {
        /** @var array{rt: array{servers: list<array{addresses: list<string>, role:string}>, ttl: int}} $route */
        $route = $bolt->route([], [], ['db' => $config->getDatabase()]);
        ['servers' => $servers, 'ttl' => $ttl] = $route['rt'];
        $ttl += time();

        return new RoutingTable($servers, $ttl);
    }

    /**
     * @throws Exception
     */
    private function useRoutingTable(V4 $bolt): RoutingTable
    {
        $bolt->run('CALL dbms.routing.getRoutingTable({context: []})');
        /** @var array{0: array{0: int, 1: list<array{addresses: list<string>, role:string}>}} */
        $response = $bolt->pull(['n' => 1]);
        $response = $response[0];
        $servers = [];
        $ttl = time() + $response[0];
        foreach ($response[1] as $server) {
            $servers[] = ['addresses' => $server['addresses'], 'role' => $server['role']];
        }

        return new RoutingTable($servers, $ttl);
    }

    /**
     * @throws Exception
     */
    private function useClusterOverview(V3 $bolt, ConnectionInterface $c): RoutingTable
    {
        try {
            $bolt->run('CALL dbms.cluster.overview()');
        } catch (MessageException $e) {
            return new RoutingTable([
                [
                    'addresses' => [(string) $c->getServerAddress()],
                    'role' => 'WRITE',
                ],
                [
                    'addresses' => [(string) $c->getServerAddress()],
                    'role' => 'READ',
                ],
            ], PHP_INT_MAX);
        }
        /** @var list<array{0: string, 1: list<string>, 2: string, 4: list, 4:string}> */
        $response = $bolt->pullAll();
        $response = array_slice($response, 0, count($response) - 1);
        $servers = [];
        $ttl = time() + 3600;

        foreach ($response as $server) {
            $addresses = $server[1];
            $addresses = array_filter($addresses, static fn (string $x) => str_starts_with($x, 'bolt://'));
            /**
             * @psalm-suppress InvalidArrayAssignment
             *
             * @var array{addresses: list<string>, role:string}
             */
            $servers[] = ['addresses' => $addresses, 'role' => $server[2]];
        }

        return new RoutingTable($servers, $ttl);
    }

    public function release(ConnectionInterface $connection): void
    {
        $this->createOrGetPool($connection->getServerAddress())->release($connection);
    }

    private function createKey(ConnectionRequestData $data): string
    {
        $uri = $data->getUri();

        $key = $data->getUserAgent().':'.$uri->getHost().':'.($uri->getPort() ?? '7687');

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
}
