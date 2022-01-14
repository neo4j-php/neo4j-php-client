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

namespace Laudis\Neo4j\Bolt;

use function array_flip;
use Bolt\Bolt;
use Bolt\connection\StreamSocket;
use Exception;
use function explode;
use const FILTER_VALIDATE_IP;
use function filter_var;
use Laudis\Neo4j\Common\BoltConnection;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Enum\SslMode;
use Laudis\Neo4j\Neo4j\RoutingTable;
use Psr\Http\Message\UriInterface;
use Throwable;
use WeakReference;

/**
 * Manages singular Bolt connections.
 *
 * @implements ConnectionPoolInterface<Bolt>
 */
final class BoltConnectionPool implements ConnectionPoolInterface
{
    /** @var array<string, list<BoltConnection>> */
    private static array $connectionCache = [];
    private DriverConfiguration $driverConfig;

    /**
     * @psalm-external-mutation-free
     */
    public function __construct(DriverConfiguration $driverConfig)
    {
        $this->driverConfig = $driverConfig;
    }

    /**
     * @throws Exception
     */
    public function acquire(
        UriInterface $uri,
        AuthenticateInterface $authenticate,
        float $socketTimeout,
        string $userAgent,
        SessionConfiguration $config,
        ?RoutingTable $table = null,
        ?UriInterface $server = null
    ): ConnectionInterface {
        $connectingTo = $server ?? $uri;
        $key = $connectingTo->getHost().':'.($connectingTo->getPort() ?? '7687');
        if (!isset(self::$connectionCache[$key])) {
            self::$connectionCache[$key] = [];
        }

        foreach (self::$connectionCache[$key] as $i => $connection) {
            if (!$connection->isOpen()) {
                $sslConfig = $connection->getDriverConfiguration()->getSslConfiguration();
                $newSslConfig = $this->driverConfig->getSslConfiguration();
                if ($sslConfig->getMode() !== $newSslConfig->getMode() ||
                    $sslConfig->isVerifyPeer() === $newSslConfig->isVerifyPeer()
                ) {
                    $connection = $this->openConnection($connectingTo, $socketTimeout, $uri, $table, $authenticate, $userAgent, $config);

                    /** @psalm-suppress PropertyTypeCoercion */
                    self::$connectionCache[$key][$i] = $connection;

                    return $connection;
                }
                $connection->open();

                $authenticate->authenticateBolt($connection->getImplementation(), $connectingTo, $userAgent);

                return $connection;
            }
        }

        $connection = $this->openConnection($connectingTo, $socketTimeout, $uri, $table, $authenticate, $userAgent, $config);

        self::$connectionCache[$key][] = $connection;

        return $connection;
    }

    private function configureSsl(UriInterface $uri, UriInterface $server, StreamSocket $socket, ?RoutingTable $table): void
    {
        $sslMode = $this->driverConfig->getSslConfiguration()->getMode();
        $sslConfig = '';
        if ($sslMode === SslMode::FROM_URL()) {
            $scheme = $uri->getScheme();
            $explosion = explode('+', $scheme, 2);
            $sslConfig = $explosion[1] ?? '';
        } elseif ($sslMode === SslMode::ENABLE()) {
            $sslConfig = 's';
        } elseif ($sslMode === SslMode::ENABLE_WITH_SELF_SIGNED()) {
            $sslConfig = 'ssc';
        }

        if (str_starts_with($sslConfig, 's')) {
            // We have to pass a different host when working with ssl on aura.
            // There is a strange behaviour where if we pass the uri host on a single
            // instance aura deployment, we need to pass the original uri for the
            // ssl configuration to be valid.
            if ($table && count($table->getWithRole()) > 1) {
                $this->enableSsl($server->getHost(), $sslConfig, $socket);
            } else {
                $this->enableSsl($uri->getHost(), $sslConfig, $socket);
            }
        }
    }

    private function enableSsl(string $host, string $sslConfig, StreamSocket $sock): void
    {
        $options = [
            'verify_peer' => $this->driverConfig->getSslConfiguration()->isVerifyPeer(),
            'peer_name' => $host,
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

    public function canConnect(UriInterface $uri, AuthenticateInterface $authenticate, ?RoutingTable $table = null, ?UriInterface $server = null): bool
    {
        $connectingTo = $server ?? $uri;
        $socket = new StreamSocket($uri->getHost(), $connectingTo->getPort() ?? 7687);

        $this->configureSsl($uri, $connectingTo, $socket, $table);

        try {
            $bolt = new Bolt($socket);
            $authenticate->authenticateBolt($bolt, $connectingTo, 'ping');
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }

    private function openConnection(
        UriInterface $connectingTo,
        float $socketTimeout,
        UriInterface $uri,
        ?RoutingTable $table,
        AuthenticateInterface $authenticate,
        string $userAgent,
        SessionConfiguration $config
    ): BoltConnection {
        $socket = new StreamSocket($connectingTo->getHost(), $connectingTo->getPort() ?? 7687, $socketTimeout);

        $this->configureSsl($uri, $connectingTo, $socket, $table);

        $bolt = new Bolt($socket);
        $authenticate->authenticateBolt($bolt, $connectingTo, $userAgent);

        // We create a weak reference to optimise the socket usage. This way the connection can reuse the bolt variable
        // the first time it tries to connect. Only when this function is finished and the returned connection is closed
        // will the reference return null, prompting the need to reopen and recreate the bolt object on the same socket.
        $originalBolt = WeakReference::create($bolt);

        /**
         * @var array{'name': 0, 'version': 1, 'edition': 2}
         * @psalm-suppress all
         */
        $fields = array_flip($bolt->run(<<<'CYPHER'
CALL dbms.components()
YIELD name, versions, edition
UNWIND versions AS version
RETURN name, version, edition
CYPHER
        )['fields']);

        /** @var array{0: array{0: string, 1: string, 2: string}} $results */
        $results = $bolt->pullAll();

        $connection = new BoltConnection(
            $results[0][$fields['name']].'-'.$results[0][$fields['edition']].'/'.$results[0][$fields['version']],
            $connectingTo,
            $results[0][$fields['version']],
            ConnectionProtocol::determineBoltVersion($bolt),
            $config->getAccessMode(),
            new DatabaseInfo($config->getDatabase()),
            $this->driverConfig,
            static function () use ($socket, $authenticate, $connectingTo, $userAgent, $originalBolt) {
                $bolt = $originalBolt->get();
                if ($bolt === null) {
                    $bolt = new Bolt($socket);
                    $authenticate->authenticateBolt($bolt, $connectingTo, $userAgent);
                }

                return $bolt;
            }
        );

        $connection->open();

        return $connection;
    }
}
