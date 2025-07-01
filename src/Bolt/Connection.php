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
use Bolt\error\ConnectionTimeoutException;
use Laudis\Neo4j\Exception\TimeoutException;

class Connection
{
    /**
     * @param ''|'s'|'ssc' $ssl
     */
    public function __construct(
        private readonly IConnection $connection,
        private readonly string $ssl,
    ) {
    }

    public function getIConnection(): IConnection
    {
        return $this->connection;
    }

    public function write(string $buffer): void
    {
        try {
            $this->connection->write($buffer);
        } catch (ConnectionTimeoutException $e) {
            throw new TimeoutException(previous: $e);
        }
    }

    public function read(int $length = 2048): string
    {
        try {
            return $this->connection->read($length);
        } catch (ConnectionTimeoutException $e) {
            throw new TimeoutException(previous: $e);
        }
    }

    public function disconnect(): void
    {
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
        $this->connection->setTimeout($timeout);
    }

    /**
     * @return ''|'s'|'ssc'
     */
    public function getEncryptionLevel(): string
    {
        return $this->ssl;
    }
}
