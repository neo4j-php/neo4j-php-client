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
use function call_user_func;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Psr\Http\Message\UriInterface;
use RuntimeException;

/**
 * @implements ConnectionInterface<Bolt>
 */
final class BoltConnection implements ConnectionInterface
{
    private ?Bolt $bolt = null;
    private string $serverAgent;
    private UriInterface $serverAddress;
    private string $serverVersion;
    private ConnectionProtocol $protocol;
    private AccessMode $accessMode;
    private DatabaseInfo $databaseInfo;
    /** @var callable(): Bolt */
    private $connector;

    /**
     * @param callable(): Bolt $connector
     */
    public function __construct(
        string $serverAgent,
        UriInterface $serverAddress,
        string $serverVersion,
        ConnectionProtocol $protocol,
        AccessMode $accessMode,
        DatabaseInfo $databaseInfo,
        $connector
    ) {
        $this->serverAgent = $serverAgent;
        $this->serverAddress = $serverAddress;
        $this->serverVersion = $serverVersion;
        $this->protocol = $protocol;
        $this->accessMode = $accessMode;
        $this->databaseInfo = $databaseInfo;
        $this->connector = $connector;
    }

    public function getImplementation(): Bolt
    {
        if ($this->bolt === null) {
            throw new RuntimeException('Connection is closed');
        }

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
        return $this->bolt !== null;
    }

    public function open(): void
    {
        if ($this->bolt === null) {
            $this->bolt = call_user_func($this->connector);
        }
    }

    public function close(): void
    {
        $this->bolt = null;
    }
}
