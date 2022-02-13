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

use Bolt\Bolt;
use Bolt\protocol\V3;
use Exception;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\BoltConnection;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Neo4j\RoutingTable;
use Psr\Http\Message\UriInterface;
use Throwable;

/**
 * Manages singular Bolt connections.
 *
 * @implements ConnectionPoolInterface<V3>
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
        SessionConfiguration $config,
        ?RoutingTable $table = null,
        ?UriInterface $server = null
    ): BoltConnection {
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
                    $connection = $this->getConnection($connectingTo, $authenticate, $config);

                    /** @psalm-suppress PropertyTypeCoercion */
                    self::$connectionCache[$key][$i] = $connection;

                    return $connection;
                }

                $connection->open();

                return $connection;
            }
        }

        $connection = $this->getConnection($connectingTo, $authenticate, $config);

        self::$connectionCache[$key][] = $connection;

        return $connection;
    }

    public function canConnect(UriInterface $uri, AuthenticateInterface $authenticate): bool
    {
        $bolt = BoltFactory::fromVariables($uri, $authenticate, $this->driverConfig);

        try {
            $bolt->build();
        } catch (Throwable $e) {
            return false;
        }

        return true;
    }

    /**
     * @throws \ReflectionException
     */
    private function getConnection(
        UriInterface $connectingTo,
        AuthenticateInterface $authenticate,
        SessionConfiguration $config
    ): BoltConnection {
        $factory = BoltFactory::fromVariables($connectingTo, $authenticate, $this->driverConfig);
        [$bolt, $response] = $factory->build();

        return new BoltConnection(
            $response['server'],
            $connectingTo,
            explode('/', $response['server'])[1] ?? '',
            ConnectionProtocol::determineBoltVersion($bolt),
            $config->getAccessMode(),
            new DatabaseInfo($config->getDatabase()),
            $factory,
            $bolt,
            $this->driverConfig
        );
    }
}
