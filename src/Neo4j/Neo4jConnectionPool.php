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

use Bolt\Bolt;
use function count;
use Exception;
use Laudis\Neo4j\Bolt\BoltConnectionPool;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Enum\RoutingRoles;
use Psr\Http\Message\UriInterface;
use function random_int;
use function str_starts_with;
use function time;

/**
 * @psalm-import-type BasicDriver from \Laudis\Neo4j\Contracts\DriverInterface
 *
 * @implements ConnectionPoolInterface<Bolt>
 */
final class Neo4jConnectionPool implements ConnectionPoolInterface
{
    /** @var array<string, RoutingTable> */
    private static array $routingCache = [];
    private BoltConnectionPool $pool;

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
        float $socketTimeout,
        string $userAgent,
        SessionConfiguration $config
    ): ConnectionInterface {
        $key = $uri->getHost().':'.($uri->getPort() ?? '7687');

        $table = self::$routingCache[$key] ?? null;
        if ($table === null || $table->getTtl() < time()) {
            $connection = $this->pool->acquire($uri, $authenticate, $socketTimeout, $userAgent, $config);
            $table = $this->routingTable($connection);
            self::$routingCache[$key] = $table;
            $connection->close();
        }

        $server = $this->getNextServer($table, $config->getAccessMode());

        $authenticate = $authenticate->extractFromUri($uri);

        return $this->pool->acquire($uri, $authenticate, $socketTimeout, $userAgent, $config, $table, $server);
    }

    /**
     * @throws Exception
     */
    private function getNextServer(RoutingTable $table, AccessMode $mode): Uri
    {
        if (AccessMode::WRITE() === $mode) {
            $servers = $table->getWithRole(RoutingRoles::LEADER());
        } else {
            $servers = $table->getWithRole(RoutingRoles::FOLLOWER());
        }

        return Uri::create($servers[random_int(0, count($servers) - 1)]);
    }

    /**
     * @param ConnectionInterface<Bolt> $driver
     *
     * @throws Exception
     */
    private function routingTable(ConnectionInterface $connection): RoutingTable
    {
        /** @var Bolt */
        $bolt = $connection->getImplementation();
        $protocol = $connection->getProtocol();
        $servers = [];
        $ttl = time() + 3600;
        if ($protocol->compare(ConnectionProtocol::BOLT_V43()) >= 0) {
            /** @var array{rt: array{servers: list<array{addresses: list<string>, role:string}>, ttl: int}} $route */
            $route = $bolt->route();
            ['servers' => $servers, 'ttl' => $ttl] = $route['rt'];
            $ttl += time();
        } elseif ($protocol->compare(ConnectionProtocol::BOLT_V40()) >= 0) {
            $bolt->run('CALL dbms.routing.getRoutingTable({context: []})');
            /**
             * @var iterable<array{addresses: list<string>, role:string}> $servers
             * @var int                       $ttl
             */

            $response = $bolt->pullAll();

            $ttl = time()+$response[0][0];
            foreach ($response[0][1] as $server) {
                $servers[] = ['addresses' => $server['addresses'], 'role' => $server['role']];
            }
        } else {
            $bolt->run('CALL dbms.cluster.overview()');
            /** @var list<array{addresses: list<string>, role: string}> */
            $response = $bolt->pullAll();

            /** @var iterable<array{addresses: list<string>, role:string}> $servers */
            foreach ($response as $server) {
                $addresses = $server['addresses'];
                $addresses = array_filter($addresses, static fn (string $x) => str_starts_with($x, 'bolt://'));
                /**
                 * @psalm-suppress InvalidArrayAssignment
                 *
                 * @var array{addresses: list<string>, role:string}
                 */
                $servers[] = ['addresses' => $addresses, 'role' => $server['role']];
            }
        }

        return new RoutingTable($servers, $ttl);
    }
}
