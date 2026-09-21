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
/** @implements ConnectionPoolInterface<BoltConnection> */
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
            BoltFactory::create($conf->getLogger(), $conf->getSocketType()),
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

    public function acquire(SessionConfiguration $config): Generator
    {
        /**
         * @var Generator<int, float, bool, BoltConnection>
         */
        return (function () use ($config) {
            $connection = $this->reuseConnectionIfPossible($config);
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

                    $connection = $this->reuseConnectionIfPossible($config);
                    if ($connection !== null) {
                        return $connection;
                    }

                    $generator->next();
                } else {
                    throw new ConnectionPoolException('Connection acquire timeout reached: '.($waitTime ?? 0.0));
                }
            }

            $connection = $this->reuseConnectionIfPossible($config);
            if ($connection !== null) {
                return $connection;
            }

            $connection = $this->factory->createConnection($this->data, $config);
            $connection->touch();

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

    private function reuseConnectionIfPossible(SessionConfiguration $config): ?BoltConnection
    {
        // Ensure random connection reuse before picking one.
        shuffle($this->activeConnections);

        // Iterate a snapshot so destroyConnection() can safely mutate activeConnections.
        foreach ([...$this->activeConnections] as $activeConnection) {
            if (!$activeConnection->isOpen() || $activeConnection->getServerState() !== 'READY') {
                if (!$activeConnection->isOpen()) {
                    $this->destroyConnection($activeConnection);
                }

                continue;
            }

            if (!$this->factory->canReuseConnection($activeConnection, $config)) {
                continue;
            }

            if ($this->needsLivenessCheck($activeConnection)) {
                if (!$this->ensureConnectionIsAlive($activeConnection)) {
                    continue;
                }
            }

            $reused = $this->factory->reuseConnection($activeConnection, $config);
            $reused->touch();

            return $reused;
        }

        return null;
    }

    private function needsLivenessCheck(BoltConnection $connection): bool
    {
        if ($this->connectionLivenessCheckTimeout === null) {
            return false;
        }

        return $connection->getIdleTimeSeconds() >= $this->connectionLivenessCheckTimeout;
    }

    /**
     * Probes the connection with RESET. On failure the connection is discarded from the pool.
     */
    private function ensureConnectionIsAlive(BoltConnection $connection): bool
    {
        try {
            $this->logger?->log(LogLevel::DEBUG, 'Running connection liveness check', [
                'idle_seconds' => $connection->getIdleTimeSeconds(),
                'threshold' => $this->connectionLivenessCheckTimeout,
            ]);
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
        try {
            $connection->invalidate();
        } catch (Throwable) {
            // Already broken; still free the pool slot below.
        }

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
