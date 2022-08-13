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
use Generator;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionFactoryInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Contracts\SemaphoreInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use function microtime;
use function shuffle;

/**
 * @implements ConnectionPoolInterface<V3>
 */
class SingleBoltConnectionPool implements ConnectionPoolInterface
{
    private SemaphoreInterface $semaphore;

    /** @var list<BoltConnection> */
    private array $activeConnections = [];
    private AuthenticateInterface $auth;
    private ConnectionFactoryInterface $factory;

    public function __construct(AuthenticateInterface $auth, SemaphoreInterface $semaphore, ConnectionFactoryInterface $factory)
    {
        $this->semaphore = $semaphore;
        $this->auth = $auth;
        $this->factory = $factory;
    }

    /**
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

        return $this->returnAnyAvailableConnection() ?? $this->factory->createConnection($this->auth, $config);
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

    private function returnAnyAvailableConnection(string $encryptionLevel): ?BoltConnection
    {
        $streamingConnection = null;
        $requiresReconnectConnection = null;
        // Ensure random connection reuse before picking one.
        shuffle($this->activeConnections);

        foreach ($this->activeConnections as $activeConnection) {
            // We prefer a connection that is just ready
            if ($activeConnection->getServerState() === 'READY') {
                if (!$this->requiresReconnect($activeConnection, $encryptionLevel)) {
                    return $activeConnection;
                } else {
                    $requiresReconnectConnection = $activeConnection;
                }
            }

            // We will store any streaming connections, so we can use that one
            // as we can force the subscribed result sets to consume the results
            // and become ready again.
            // This code will make sure we never get stuck if the user has many
            // results open that aren't consumed yet.
            // https://github.com/neo4j-php/neo4j-php-client/issues/146
            // NOTE: we cannot work with TX_STREAMING as we cannot force the transaction to implicitly close.
            if ($streamingConnection === null && $activeConnection->getServerState() === 'STREAMING') {
                if (!$this->requiresReconnect($activeConnection, $encryptionLevel)) {
                    $streamingConnection = $activeConnection;
                    $streamingConnection->consumeResults(); // State should now be ready
                } else {
                    $requiresReconnectConnection = $activeConnection;
                }
            }
        }

        if ($streamingConnection) {
            return $streamingConnection;
        }

        if ($requiresReconnectConnection) {
            $this->release($requiresReconnectConnection);

            return $this->createNewConnection();
        }

        return null;
    }

    private function requiresReconnect(BoltConnection $activeConnection, string $requiredEncryptionLevel): bool
    {
        return
    }
}
