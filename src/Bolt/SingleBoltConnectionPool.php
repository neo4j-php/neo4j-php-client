<?php

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Bolt;

use function explode;
use function extension_loaded;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Common\SingleThreadedSemaphore;
use Laudis\Neo4j\Common\SysVSemaphore;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\SemaphoreInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use function microtime;
use Psr\Http\Message\UriInterface;
use RuntimeException;

class SingleBoltConnectionPool
{
    private SemaphoreInterface $semaphore;
    private UriInterface $uri;

    /** @var list<BoltConnection> */
    private array $activeConnections = [];
    private DriverConfiguration $config;

    public function __construct(UriInterface $uri, DriverConfiguration $config)
    {
        $key = $uri->getHost().':'.$uri->getPort().':'.$config->getUserAgent();
        if (extension_loaded('ext-sysvsem')) {
            $this->semaphore = SysVSemaphore::create($key, $config->getMaxPoolSize());
        } else {
            $this->semaphore = SingleThreadedSemaphore::create($key, $config->getMaxPoolSize());
        }

        $this->uri = $uri;
        $this->config = $config;
    }

    public function acquire(AuthenticateInterface $auth, SessionConfiguration $config): BoltConnection
    {
        $generator = $this->semaphore->wait();
        $start = microtime(true);

        while ($generator->valid()) {
            $generator->next();
            $this->guardTiming($start);

            $connection = $this->returnAnyAvailableConnection();
            if ($connection !== null) {
                return $connection;
            }
        }

        return $this->returnAnyAvailableConnection() ?? $this->createNewConnection($auth, $config);
    }

    public function release(BoltConnection $connection): void
    {
        $this->semaphore->post();
        $connection->close();

        foreach ($this->activeConnections as $i => $activeConnection) {
            if ($connection === $activeConnection) {
                array_splice($this->activeConnections, $i, 1);

                return;
            }
        }
    }

    /**
     * @throws RuntimeException
     */
    private function guardTiming(float $start): void
    {
        $elapsed = microtime(true) - $start;
        if ($elapsed > $this->config->getAcquireConnectionTimeout()) {
            throw new RuntimeException(sprintf('Connection to %s timed out after %s seconds', $this->uri->__toString(), $elapsed));
        }
    }

    private function returnAnyAvailableConnection(): ?BoltConnection
    {
        foreach ($this->activeConnections as $activeConnection) {
            if ($activeConnection->getServerAgent() === 'READY') {
                return $activeConnection;
            }
        }

        return null;
    }

    private function createNewConnection(
        AuthenticateInterface $authenticate,
        SessionConfiguration $config
    ): BoltConnection {
        $factory = BoltFactory::fromVariables($this->uri, $authenticate, $this->config);
        [$bolt, $response] = $factory->build();

        $config = new ConnectionConfiguration(
            $response['server'],
            $this->uri,
            explode('/', $response['server'])[1] ?? '',
            ConnectionProtocol::determineBoltVersion($bolt),
            $config->getAccessMode(),
            $this->config,
            $config->getDatabase() === null ? null : new DatabaseInfo($config->getDatabase())
        );

        $tbr = new BoltConnection($factory, $bolt, $config);

        $this->activeConnections[] = $tbr;

        return $tbr;
    }
}
