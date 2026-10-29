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
use Laudis\Neo4j\Exception\ConnectionPoolException;
use Psr\Http\Message\UriInterface;
use Psr\Log\LogLevel;

use function shuffle;

use Throwable;

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
        private readonly ?Neo4jLogger $logger,
        private readonly float $acquireConnectionTimeout,
        private readonly ?float $connectionLivenessCheckTimeout = null,
    ) {
    }

    public static function create(
        UriInterface $uri,
        AuthenticateInterface $auth,
        DriverConfiguration $conf,
        SemaphoreInterface $semaphore,
    ): self {
        return new self(
            $semaphore,
            BoltFactory::create($conf->getLogger(), $conf->getSocketType(), $conf->getClock()),
            new ConnectionRequestData(
                $uri->getHost(),
                $uri,
                $auth,
                $conf->getUserAgent(),
                $conf->getSslConfiguration(),
                $conf->getSocketTimeoutSecondsExplicit(),
                $conf->isTelemetryEnabled(),
            ),
            $conf->getLogger(),
            $conf->getAcquireConnectionTimeout(),
            $conf->getConnectionLivenessCheckTimeout(),
        );
    }

    public function acquire(SessionConfiguration $config, bool $forRouting = false): Generator
    {
        /**
         * @var Generator<int, float, bool, BoltConnection>
         */
        return (function () use ($config, $forRouting) {
            $connection = $this->reuseConnectionIfPossible($config, $forRouting);
            if ($connection !== null) {
                return $connection;
            }

            $generator = $this->semaphore->wait();
            // If the generator is valid, it means we are waiting to acquire a new connection.
            // This means we can use this time to check if we can reuse a connection or should throw a timeout exception.
            while ($generator->valid()) {
                $waitTime = $generator->current();
                if ($waitTime <= $this->acquireConnectionTimeout) {
                    yield $waitTime;

                    $connection = $this->reuseConnectionIfPossible($config, $forRouting);
                    if ($connection !== null) {
                        return $connection;
                    }

                    $generator->next();
                } else {
                    throw new ConnectionPoolException('Connection acquire timeout reached: '.($waitTime ?? 0.0));
                }
            }

            $connection = $this->reuseConnectionIfPossible($config, $forRouting);
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
        foreach ($this->activeConnections as $i => $activeConnection) {
            if ($connection === $activeConnection) {
                array_splice($this->activeConnections, $i, 1);

                $this->semaphore->post();

                return;
            }
        }
    }

    public function getLogger(): ?Neo4jLogger
    {
        return $this->logger;
    }

    private function reuseConnectionIfPossible(SessionConfiguration $config, bool $forRouting = false): ?BoltConnection
    {
        // Ensure random connection reuse before picking one.
        shuffle($this->activeConnections);

        // Iterate a snapshot so destroyConnection() can safely mutate activeConnections.
        // Only reuse READY connections. STREAMING connections must stay open so nested
        // session.run can use a second connection (TestKit nested iteration). Batch
        // runStatements already releases locks on one connection via Session (#283).
        foreach ([...$this->activeConnections] as $activeConnection) {
            if (!$activeConnection->isOpen() || $activeConnection->getServerState() !== 'READY') {
                if (!$activeConnection->isOpen()) {
                    $this->destroyConnection($activeConnection);
                }

                continue;
            }

            // ROUTE is not bound to the session database or access mode that opened the
            // socket. A later session with no database must reuse this router connection:
            // the contact-point stub accepts one connection, and a second handshake fails.
            if (!$forRouting && !$this->factory->canReuseConnection($activeConnection, $config)) {
                continue;
            }

            if (!$this->resetIfIdle($activeConnection)) {
                continue;
            }

            return $this->factory->reuseConnection($activeConnection, $config);
        }

        return null;
    }

    /**
     * When the connection has been idle past the liveness timeout, probe it with RESET.
     * Returns false after a failed probe; that connection is already removed from the pool.
     */
    private function resetIfIdle(BoltConnection $connection): bool
    {
        $timeout = $this->connectionLivenessCheckTimeout;
        if ($timeout === null || $connection->getIdleTimeSeconds() < $timeout) {
            return true;
        }

        try {
            $connection->reset();

            return true;
        } catch (Throwable $e) {
            $this->logger?->log(LogLevel::WARNING, 'Connection failed liveness check, discarding', [
                'error' => $e->getMessage(),
            ]);
            $this->destroyConnection($connection);

            return false;
        }
    }

    private function destroyConnection(BoltConnection $connection): void
    {
        $connection->invalidate();
        $this->release($connection);
    }

    public function close(): void
    {
        $activeCount = count($this->activeConnections);
        foreach ($this->activeConnections as $activeConnection) {
            $activeConnection->close();
        }
        $this->activeConnections = [];

        for ($i = 0; $i < $activeCount; ++$i) {
            $this->semaphore->post();
        }
    }
}
