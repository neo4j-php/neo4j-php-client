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

namespace Laudis\Neo4j\Neo4j;

use function array_slice;
use function array_unique;
use Bolt\protocol\V3;
use Bolt\protocol\V4;
use Bolt\protocol\V4_3;
use Bolt\protocol\V4_4;
use function count;
use Exception;
use Laudis\Neo4j\Bolt\BoltConnectionPool;
use Laudis\Neo4j\Common\BoltConnection;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\RoutingRoles;
use Psr\Http\Message\UriInterface;
use function random_int;
use function str_starts_with;
use function time;

/**
 * Connection pool for with auto client-side routing.
 *
 * @psalm-import-type BasicDriver from \Laudis\Neo4j\Contracts\DriverInterface
 *
 * @implements ConnectionPoolInterface<V3>
 */
final class Neo4jConnectionPool implements ConnectionPoolInterface
{
    /**
     * @psalm-readonly
     *
     * @var array<string, RoutingTable>
     */
    private static array $routingCache = [];
    /** @psalm-readonly */
    private BoltConnectionPool $pool;

    /**
     * @psalm-mutation-free
     */
    public function __construct(BoltConnectionPool $pool)
    {
        $this->pool = $pool;
    }

    /**
     * @throws Exception
     */
    public function acquire(
        UriInterface $uri,
        AuthenticateInterface $authenticate,
        SessionConfiguration $config
    ): BoltConnection {
        $key = $uri->getHost().':'.($uri->getPort() ?? '7687');

        $table = self::$routingCache[$key] ?? null;
        if ($table === null || $table->getTtl() < time()) {
            $connection = $this->pool->acquire($uri, $authenticate, $config);
            $table = $this->routingTable($connection, $config);
            self::$routingCache[$key] = $table;
            $connection->close();
        }

        $server = $this->getNextServer($table, $config->getAccessMode()) ?? $uri;

        if ($server->getScheme() === '') {
            $server = $server->withScheme($uri->getScheme());
        }

        return $this->pool->acquire($uri, $authenticate, $config, $table, $server);
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

        return $this->useClusterOverview($bolt);
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
    private function useClusterOverview(V3 $bolt): RoutingTable
    {
        $bolt->run('CALL dbms.cluster.overview()');
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

    public function canConnect(UriInterface $uri, AuthenticateInterface $authenticate): bool
    {
        return $this->pool->canConnect($uri, $authenticate);
    }
}
