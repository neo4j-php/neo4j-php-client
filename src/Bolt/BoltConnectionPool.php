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
use Laudis\Neo4j\Common\SingleThreadedSemaphore;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Neo4j\RoutingTable;
use function microtime;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Throwable;

/**
 * Manages singular Bolt connections.
 *
 * @implements ConnectionPoolInterface<V3>
 */
final class BoltConnectionPool implements ConnectionPoolInterface
{
    private DriverConfiguration $driverConfig;
    /** @var array<string, list<BoltConnection>> */
    private static array $activeConnections = [];

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

        $key = $this->generateKey($connectingTo);
        $semaphore = SingleThreadedSemaphore::create($key, $this->driverConfig->getMaxPoolSize());


        $connection ??= $this->getConnection($uri, $authenticate, $config, $key);

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



    private function compare(BoltConnection $connection, AuthenticateInterface $authenticate): bool
    {
        $sslConfig = $connection->getDriverConfiguration()->getSslConfiguration();
        $newSslConfig = $this->driverConfig->getSslConfiguration();

        return $sslConfig->getMode() !== $newSslConfig->getMode() ||
            $sslConfig->isVerifyPeer() === $newSslConfig->isVerifyPeer() ||
            $authenticate !== $connection->getFactory()->getAuth();
    }


    private function generateKey(UriInterface $connectingTo): string
    {
        return $this->driverConfig->getUserAgent().'|'.$connectingTo->getHost().'|'.($connectingTo->getPort() ?? '7687');
    }
}
