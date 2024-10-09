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

use Generator;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Contracts\SemaphoreInterface;
use Laudis\Neo4j\Databags\ConnectionRequestData;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;

use function method_exists;
use function microtime;

use Psr\Http\Message\UriInterface;

use function shuffle;

/**
 * @implements ConnectionPoolInterface<BoltConnection>
 */
final class ConnectionPool implements ConnectionPoolInterface
{
    /** @var list<BoltConnection> */
    private array $activeConnections = [];

    public function __construct(
        private readonly SemaphoreInterface $semaphore,
        private readonly BoltFactory $factory,
        private readonly ConnectionRequestData $data,
        private readonly ?Neo4jLogger $logger
    ) {}

    public static function create(
        UriInterface $uri,
        AuthenticateInterface $auth,
        DriverConfiguration $conf,
        SemaphoreInterface $semaphore
    ): self {
        return new self(
            $semaphore,
            BoltFactory::create($conf->getLogger()),
            new ConnectionRequestData(
                $uri->getHost(),
                $uri,
                $auth,
                $conf->getUserAgent(),
                $conf->getSslConfiguration()
            ),
            $conf->getLogger()
        );
    }

    public function acquire(SessionConfiguration $config): Generator
    {
        $generator = $this->semaphore->wait();
        $start = microtime(true);

        return (function () use ($generator, $start, $config) {
            // If the generator is valid, it means we are waiting to acquire a new connection.
            // This means we can use this time to check if we can reuse a connection or should throw a timeout exception.
            while ($generator->valid()) {
                /** @var bool $continue */
                $continue = yield microtime(true) - $start;
                $generator->send($continue);
                if ($continue === false) {
                    return null;
                }

                $connection = $this->returnAnyAvailableConnection($config);
                if ($connection !== null) {
                    return $connection;
                }
            }

            $connection = $this->returnAnyAvailableConnection($config);
            if ($connection !== null) {
                return $connection;
            }

            $connection = $this->factory->createConnection($this->data, $config);
            $this->activeConnections[] = $connection;

            return $connection;
        })();
    }

    public function release(ConnectionInterface $connection): void
    {
        $this->semaphore->post();

        foreach ($this->activeConnections as $i => $activeConnection) {
            if ($connection === $activeConnection) {
                array_splice($this->activeConnections, $i, 1);

                return;
            }
        }
    }

    public function getLogger(): ?Neo4jLogger
    {
        return $this->logger;
    }

    /**
     * @return BoltConnection|null
     */
    private function returnAnyAvailableConnection(SessionConfiguration $config): ?ConnectionInterface
    {
        $streamingConnection = null;
        $requiresReconnectConnection = null;
        // Ensure random connection reuse before picking one.
        shuffle($this->activeConnections);

        foreach ($this->activeConnections as $activeConnection) {
            // We prefer a connection that is just ready
            if ($activeConnection->getServerState() === 'READY') {
                if ($this->factory->canReuseConnection($activeConnection, $this->data, $config)) {
                    return $this->factory->reuseConnection($activeConnection, $config);
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
                if ($this->factory->canReuseConnection($activeConnection, $this->data, $config)) {
                    $streamingConnection = $activeConnection;
                    if (method_exists($streamingConnection, 'consumeResults')) {
                        $streamingConnection->consumeResults(); // State should now be ready
                    }
                } else {
                    $requiresReconnectConnection = $activeConnection;
                }
            }
        }

        if ($streamingConnection) {
            return $this->factory->reuseConnection($streamingConnection, $config);
        }

        if ($requiresReconnectConnection) {
            $this->release($requiresReconnectConnection);

            return $this->factory->createConnection($this->data, $config);
        }

        return null;
    }

    public function close(): void
    {
        foreach ($this->activeConnections as $activeConnection) {
            $activeConnection->close();
        }
        $this->activeConnections = [];
    }
}
