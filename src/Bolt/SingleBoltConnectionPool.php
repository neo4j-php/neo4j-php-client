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

use Bolt\protocol\V3;
use function explode;
use function extension_loaded;
use Generator;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Common\SingleThreadedSemaphore;
use Laudis\Neo4j\Common\SysVSemaphore;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Contracts\SemaphoreInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use function microtime;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use function shuffle;

/**
 * @implements ConnectionPoolInterface<V3>
 */
class SingleBoltConnectionPool implements ConnectionPoolInterface
{
    private SemaphoreInterface $semaphore;
    private UriInterface $uri;

    /** @var list<BoltConnection> */
    private array $activeConnections = [];
    private DriverConfiguration $config;
    private AuthenticateInterface $auth;

    public function __construct(UriInterface $uri, DriverConfiguration $config, AuthenticateInterface $auth)
    {
        // Because interprocess switching of connections between PHP sessions is impossible,
        // we have to build a key to limit the amount of open connections, potentially between ALL sessions.
        // because of this we have to settle on a configuration basis to limit the connection pool,
        // not on an object basis.
        // The combination is between the server and the user agent as it most closely resembles an "application"
        // connecting to a server. The application thus supports multiple authentication methods, but they have
        // to be shared between the same connection pool.
        $key = $uri->getHost().':'.$uri->getPort().':'.$config->getUserAgent();
        if (extension_loaded('ext-sysvsem')) {
            $this->semaphore = SysVSemaphore::create($key, $config->getMaxPoolSize());
        } else {
            $this->semaphore = SingleThreadedSemaphore::create($key, $config->getMaxPoolSize());
        }

        $this->uri = $uri;
        $this->auth = $auth;
    }

    /**
     * @param SessionConfiguration $config
     *
     * @return Generator<
     *      int,
     *      float,
     *      bool,
     *      BoltConnection|null
     * >
     */
    public function acquire(SessionConfiguration $config): Generator
    {
        $generator = $this->semaphore->wait();
        $start = microtime(true);

        // If the generator is valid, it means we are waiting to acquire a new connection.
        // This means we can use this time to check if we can reuse a connection or should throw a timeout exception.
        while ($generator->valid()) {
            $continue = yield microtime(true) - $start;
            $generator->send($continue);
            if ($continue === false) {
                return null;
            }

            $connection = $this->returnAnyAvailableConnection();
            if ($connection !== null) {
                return $connection;
            }
        }

        return $this->returnAnyAvailableConnection() ?? $this->createNewConnection($config);
    }

    public function release(ConnectionInterface $connection): void
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



    private function returnAnyAvailableConnection(): ?BoltConnection
    {
        $streamingConnection = null;
        // Ensure random connection reuse before picking one.
        shuffle($this->activeConnections);

        foreach ($this->activeConnections as $activeConnection) {
            // We prefer a connection that is just ready
            if ($activeConnection->getServerState() === 'READY') {
                return $activeConnection;
            }

            // We will store any streaming connections, so we can use that one
            // as we can force the subscribed result sets to consume the results
            // and become ready again.
            // This code will make sure we never get stuck if the user has many
            // results open that aren't consumed yet.
            // https://github.com/neo4j-php/neo4j-php-client/issues/146
            // NOTE: we cannot work with TX_STREAMING as we cannot force the transaction to implicitly close.
            if ($streamingConnection === null && $activeConnection->getServerState() === 'STREAMING') {
                $streamingConnection = $activeConnection;
                $streamingConnection->consumeResults(); // State should now be ready
            }
        }

        return $streamingConnection;
    }

    private function createNewConnection(SessionConfiguration $config): BoltConnection
    {
        $factory = BoltFactory::fromVariables($this->uri, $this->auth, $this->config);
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
