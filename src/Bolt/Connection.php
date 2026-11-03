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

use Bolt\connection\IConnection;
use Bolt\error\ConnectException as BoltConnectException;
use Psr\Log\LoggerInterface;
use Throwable;

class Connection
{
    private bool $disconnected = false;

    /**
     * @param ''|'s'|'ssc' $ssl
     */
    public function __construct(
        private readonly IConnection $connection,
        private readonly string $ssl,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function getIConnection(): IConnection
    {
        return $this->connection;
    }

    public function write(string $buffer): void
    {
        $this->connection->write($buffer);
    }

    public function read(int $length = 2048): string
    {
        $data = $this->connection->read($length);

        // Detect EOF - empty read from blocking socket indicates connection closed
        if ($data === '' && $length > 0) {
            throw new BoltConnectException('Connection closed by remote host');
        }

        return $data;
    }

    public function disconnect(): void
    {
        // Idempotent: calling disconnect twice on the same underlying socket is what
        // throws (not "server already closed"). Mark before the call so a failed first
        // attempt cannot be retried into a second shutdown on the same socket.
        if ($this->disconnected) {
            return;
        }

        $this->disconnected = true;
        $this->connection->disconnect();
    }

    public function getIp(): string
    {
        return $this->connection->getIp();
    }

    public function getPort(): int
    {
        return $this->connection->getPort();
    }

    public function getTimeout(): float
    {
        return $this->connection->getTimeout();
    }

    public function setTimeout(float $timeout): void
    {
        try {
            $this->connection->setTimeout($timeout);
        } catch (Throwable $e) {
            $this->logger?->warning('Failed to set socket timeout on connection', [
                'timeout' => $timeout,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return ''|'s'|'ssc'
     */
    public function getEncryptionLevel(): string
    {
        return $this->ssl;
    }
}
