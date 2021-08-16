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

use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Psr\Http\Message\UriInterface;

/**
 * @template T
 * @implements ConnectionInterface<T>
 */
final class Connection implements ConnectionInterface
{
    /** @var T */
    private $socket;
    private string $serverAgent;
    private UriInterface $serverAddress;
    private string $serverVersion;
    private ConnectionProtocol $protocol;
    private AccessMode $accessMode;
    private DatabaseInfo $databaseInfo;

    /**
     * @param T $socket
     */
    public function __construct(
        $socket,
        string $serverAgent,
        UriInterface $serverAddress,
        string $serverVersion,
        ConnectionProtocol $protocol,
        AccessMode $accessMode,
        DatabaseInfo $databaseInfo
    ) {
        $this->socket = $socket;
        $this->serverAgent = $serverAgent;
        $this->serverAddress = $serverAddress;
        $this->serverVersion = $serverVersion;
        $this->protocol = $protocol;
        $this->accessMode = $accessMode;
        $this->databaseInfo = $databaseInfo;
    }

    public function getImplementation()
    {
        return $this->socket;
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
}
