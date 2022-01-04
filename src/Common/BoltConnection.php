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

use Bolt\protocol\V3;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * @implements ConnectionInterface<V3>
 */
final class BoltConnection implements ConnectionInterface
{
    private ?V3 $connection;
    /** @psalm-readonly */
    private string $serverAgent;
    /** @psalm-readonly */
    private UriInterface $serverAddress;
    /** @psalm-readonly */
    private string $serverVersion;
    /** @psalm-readonly */
    private ConnectionProtocol $protocol;
    /** @psalm-readonly */
    private AccessMode $accessMode;
    /** @psalm-readonly */
    private DatabaseInfo $databaseInfo;
    /** @psalm-readonly */
    private BoltFactory $factory;

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        string $serverAgent,
        UriInterface $serverAddress,
        string $serverVersion,
        ConnectionProtocol $protocol,
        AccessMode $accessMode,
        DatabaseInfo $databaseInfo,
        BoltFactory $factory,
        ?V3 $connection
    ) {
        $this->serverAgent = $serverAgent;
        $this->serverAddress = $serverAddress;
        $this->serverVersion = $serverVersion;
        $this->protocol = $protocol;
        $this->accessMode = $accessMode;
        $this->databaseInfo = $databaseInfo;
        $this->factory = $factory;
        $this->connection = $connection;
    }

    /**
     * @psalm-mutation-free
     */
    public function getImplementation(): V3
    {
        if ($this->connection === null) {
            throw new RuntimeException('Connection is closed');
        }

        return $this->connection;
    }

    /**
     * @psalm-mutation-free
     */
    public function getServerAgent(): string
    {
        return $this->serverAgent;
    }

    /**
     * @psalm-mutation-free
     */
    public function getServerAddress(): UriInterface
    {
        return $this->serverAddress;
    }

    /**
     * @psalm-mutation-free
     */
    public function getServerVersion(): string
    {
        return $this->serverVersion;
    }

    /**
     * @psalm-mutation-free
     */
    public function getProtocol(): ConnectionProtocol
    {
        return $this->protocol;
    }

    /**
     * @psalm-mutation-free
     */
    public function getAccessMode(): AccessMode
    {
        return $this->accessMode;
    }

    /**
     * @psalm-mutation-free
     */
    public function getDatabaseInfo(): DatabaseInfo
    {
        return $this->databaseInfo;
    }

    /**
     * @psalm-mutation-free
     */
    public function isOpen(): bool
    {
        return $this->connection !== null;
    }

    public function open(): void
    {
        if ($this->connection === null) {
            $this->connection = $this->factory->build();
        }
    }

    public function close(): void
    {
        $this->connection = null;
    }
}
