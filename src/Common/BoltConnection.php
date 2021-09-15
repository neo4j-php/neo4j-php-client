<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Common;

use Bolt\Bolt;
use Bolt\connection\IConnection;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Psr\Http\Message\UriInterface;

/**
 * @implements ConnectionInterface<Bolt>
 */
final class BoltConnection implements ConnectionInterface
{
    private Bolt $bolt;
    private string $serverAgent;
    private UriInterface $serverAddress;
    private string $serverVersion;
    private ConnectionProtocol $protocol;
    private AccessMode $accessMode;
    private DatabaseInfo $databaseInfo;
    private IConnection $socket;

    private bool $isOpen = true;

    public function __construct(
        Bolt $bolt,
        IConnection $socket,
        string $serverAgent,
        UriInterface $serverAddress,
        string $serverVersion,
        ConnectionProtocol $protocol,
        AccessMode $accessMode,
        DatabaseInfo $databaseInfo
    ) {
        $this->bolt = $bolt;
        $this->serverAgent = $serverAgent;
        $this->serverAddress = $serverAddress;
        $this->serverVersion = $serverVersion;
        $this->protocol = $protocol;
        $this->accessMode = $accessMode;
        $this->databaseInfo = $databaseInfo;
        $this->socket = $socket;
    }

    public function getImplementation(): Bolt
    {
        return $this->bolt;
    }

    public function getServerAgent(): string
    {
        return $this->serverAgent;
    }

    public function getServerAddress(): UriInterface
    {
        return $this->serverAddress;
    }

    public function getServerVersion(): string
    {
        return $this->serverVersion;
    }

    public function getProtocol(): ConnectionProtocol
    {
        return $this->protocol;
    }

    public function getAccessMode(): AccessMode
    {
        return $this->accessMode;
    }

    public function getDatabaseInfo(): DatabaseInfo
    {
        return $this->databaseInfo;
    }

    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    public function open(): void
    {
        if (!$this->isOpen) {
            $this->isOpen = true;
        }
    }

    public function close(): void
    {
        if ($this->isOpen) {
            $this->isOpen = false;
        }
    }
}
