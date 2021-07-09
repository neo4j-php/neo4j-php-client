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

use Bolt\connection\StreamSocket;
use Exception;
use function explode;
use const FILTER_VALIDATE_IP;
use function filter_var;
use Laudis\Neo4j\Bolt\BoltDriver;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\RoutingRoles;
use Laudis\Neo4j\Types\CypherList;
use Psr\Http\Message\UriInterface;
use function random_int;
use function str_starts_with;
use function time;

/**
 * @psalm-import-type BasicDriver from \Laudis\Neo4j\Contracts\DriverInterface
 *
 * @implements ConnectionPoolInterface<StreamSocket>
 */
final class Neo4jConnectionPool implements ConnectionPoolInterface
{
    private ?RoutingTable $table = null;
    /** @var ConnectionPoolInterface<StreamSocket> */
    private ConnectionPoolInterface $pool;

    /**
     * @param ConnectionPoolInterface<StreamSocket> $pool
     */
    public function __construct(ConnectionPoolInterface $pool)
    {
        $this->pool = $pool;
    }

    /**
     * @throws Exception
     */
    public function acquire(UriInterface $uri, AccessMode $mode, AuthenticateInterface $authenticate): StreamSocket
    {
        $table = $this->routingTable($uri, $authenticate);
        $server = $this->getNextServer($table, $mode);

        $socket = $this->pool->acquire(Uri::create($server), $mode, $authenticate);

        $scheme = $uri->getScheme();
        $explosion = explode('+', $scheme, 2);
        $sslConfig = $explosion[1] ?? '';

        if (str_starts_with('s', $sslConfig)) {
            $this->enableSsl($server, $sslConfig, $socket, $uri);
        }

        return $socket;
    }

    private function enableSsl(string $host, string $sslConfig, StreamSocket $sock, UriInterface $uri): void
    {
        // Pass a standard option to enable ssl as there is no direct flag
        // and \Bolt\Bolt only turns on ssl if an option is passed.
        $options = [
            'verify_peer' => true,
            'peer_name' => $uri->getHost(),
        ];
        if (!filter_var($host, FILTER_VALIDATE_IP)) {
            $options['SNI_enabled'] = true;
        }
        if ($sslConfig === 's') {
            $sock->setSslContextOptions($options);
        } elseif ($sslConfig === 'ssc') {
            $options['allow_self_signed'] = true;
            $sock->setSslContextOptions($options);
        }
    }

    /**
     * @throws Exception
     */
    private function getNextServer(RoutingTable $table, AccessMode $mode): string
    {
        if (AccessMode::WRITE() === $mode) {
            $servers = $table->getWithRole(RoutingRoles::LEADER());
        } else {
            $servers = $table->getWithRole(RoutingRoles::FOLLOWER());
        }

        return $servers->get(random_int(0, $servers->count() - 1));
    }

    /**
     * @param BasicDriver $driver
     *
     * @throws Exception
     */
    private function routingTable(UriInterface $uri, AuthenticateInterface $authenticate): RoutingTable
    {
        if ($this->table === null || $this->table->getTtl() < time()) {
            $session = BoltDriver::create($uri, null, $authenticate)->createSession();
            $row = $session->run(
                'CALL dbms.components() yield versions UNWIND versions as version RETURN version'
            )->first();
            /** @var string */
            $version = $row->get('version');

            if (str_starts_with($version, '3')) {
                $response = $session->run('CALL dbms.cluster.overview()');

                /** @var iterable<array{addresses: list<string>, role:string}> $values */
                $values = [];
                foreach ($response as $server) {
                    /** @var CypherList<string> $addresses */
                    $addresses = $server->get('addresses');
                    $addresses = $addresses->filter(static fn (string $x) => str_starts_with($x, 'bolt://'));
                    /**
                     * @psalm-suppress InvalidArrayAssignment
                     *
                     * @var array{addresses: list<string>, role:string}
                     */
                    $values[] = ['addresses' => $addresses->toArray(), 'role' => $server->get('role')];
                }

                $this->table = new RoutingTable($values, time() + 3600);
            } else {
                $response = $session->run('CALL dbms.routing.getRoutingTable({context: []})')->first();
                /** @var iterable<array{addresses: list<string>, role:string}> $values */
                $values = $response->get('servers');
                /** @var int $ttl */
                $ttl = $response->get('ttl');

                $this->table = new RoutingTable($values, time() + $ttl);
            }
        }

        return $this->table;
    }
}
