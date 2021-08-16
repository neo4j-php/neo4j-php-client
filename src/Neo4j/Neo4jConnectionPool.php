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
use Bolt\connection\StreamSocket;
use Exception;
use function explode;
use Laudis\Neo4j\Bolt\BoltDriver;
use Laudis\Neo4j\Common\TransactionHelper;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
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
 * @implements ConnectionPoolInterface<Bolt>
 */
final class Neo4jConnectionPool implements ConnectionPoolInterface
{
    private ?RoutingTable $table = null;

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
        $table = $this->routingTable($uri, $authenticate);
        $server = $this->getNextServer($table, $config->getAccessMode());

        $socket = new StreamSocket($server->getHost(), $server->getPort() ?? 7687, $socketTimeout);

        $this->configureSsl($uri, $table, $server, $socket);

        return TransactionHelper::connectionFromSocket($socket, $uri, $userAgent, $authenticate, $config);
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

        return Uri::create($servers->get(random_int(0, $servers->count() - 1)));
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
            $row = $session->run(<<<'CYPHER'
CALL dbms.components() YIELD versions UNWIND versions AS version RETURN version
CYPHER
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

    private function configureSsl(UriInterface $uri, RoutingTable $table, Uri $server, StreamSocket $socket): void
    {
        $scheme = $uri->getScheme();
        $explosion = explode('+', $scheme, 2);
        $sslConfig = $explosion[1] ?? '';

        if (str_starts_with('s', $sslConfig)) {
            // We have to pass a different host when working with ssl on aura.
            // There is a strange behaviour where if we pass the uri host on a single
            // instance aura deployment, we need to pass the original uri for the
            // ssl configuration to be valid.
            if ($table->getWithRole()->count() > 1) {
                TransactionHelper::enableSsl($server->getHost(), $sslConfig, $socket);
            } else {
                TransactionHelper::enableSsl($uri->getHost(), $sslConfig, $socket);
            }
        }
    }
}
