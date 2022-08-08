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

namespace Laudis\Neo4j\Bolt;

use Bolt\protocol\V3;
use Exception;
use function explode;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Neo4j\RoutingTable;
use Psr\Http\Message\UriInterface;
use Psr\SimpleCache\CacheInterface;
use function shuffle;
use Throwable;

/**
 * Manages singular Bolt connections.
 *
 * @implements ConnectionPoolInterface<V3>
 */
final class BoltConnectionPool implements ConnectionPoolInterface
{
    private DriverConfiguration $driverConfig;
    private CacheInterface $cache;

    /**
     * @psalm-external-mutation-free
     */
    public function __construct(DriverConfiguration $driverConfig, CacheInterface $cache)
    {
        $this->driverConfig = $driverConfig;
        $this->cache = $cache;
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
        $keys = $this->generateKeys($connectingTo);
        while (true) {
            foreach ($this->cache->getMultiple($keys) as $key => $connection) {
                // TODO - generate some locking of some sort.
                // There are lots of ways to achieve this but none is perfect.
                // The main contenders at the moment are:
                // - leveraging the file system locks (slow, error prone, but no external dependencies)
                // - use the semaphore (fast, but requires external dependencies)
                // - use the lock library https://packagist.org/packages/malkusch/lock (flexible, but a lot more complicated)
                $connection ??= $this->getConnection($uri, $authenticate, $config);
                if (!$connection->isOpen()) {
                    if ($this->compare($connection, $authenticate)) {
                        $connection = $this->getConnection($connectingTo, $authenticate, $config);

                        $this->cache->set($key, $connection);

                        return $connection;
                    }

                    $connection->open();

                    return $connection;
                }

                if ($connection->getServerState() === 'READY' && $authenticate === $connection->getFactory()->getAuth(
                    )) {
                    return $connection;
                }
            }
        }
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

    private function getConnection(
        UriInterface $connectingTo,
        AuthenticateInterface $authenticate,
        SessionConfiguration $config
    ): BoltConnection {
        $factory = BoltFactory::fromVariables($connectingTo, $authenticate, $this->driverConfig);
        [$bolt, $response] = $factory->build();

        $config = new ConnectionConfiguration(
            $response['server'],
            $connectingTo,
            explode('/', $response['server'])[1] ?? '',
            ConnectionProtocol::determineBoltVersion($bolt),
            $config->getAccessMode(),
            $this->driverConfig,
            $config->getDatabase() === null ? null : new DatabaseInfo($config->getDatabase())
        );

        return new BoltConnection($factory, $bolt, $config);
    }

    private function compare(BoltConnection $connection, AuthenticateInterface $authenticate): bool
    {
        $sslConfig = $connection->getDriverConfiguration()->getSslConfiguration();
        $newSslConfig = $this->driverConfig->getSslConfiguration();

        return $sslConfig->getMode() !== $newSslConfig->getMode() ||
            $sslConfig->isVerifyPeer() === $newSslConfig->isVerifyPeer() ||
            $authenticate !== $connection->getFactory()->getAuth();
    }

    private function generateKeys(UriInterface $connectingTo): \Generator
    {
        $key = $this->driverConfig->getUserAgent().':'.$connectingTo->getHost().':'.($connectingTo->getPort() ?? '7687');
        $ranges = range(0, max(0, $this->driverConfig->getMaxPoolSize() - 1));
        shuffle($ranges);

        foreach ($ranges as $range) {
            yield $key.':'.$range;
        }
    }
}
