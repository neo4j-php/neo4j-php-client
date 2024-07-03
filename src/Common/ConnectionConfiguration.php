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

namespace Laudis\Neo4j\Common;

use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Psr\Http\Message\UriInterface;

/**
 * @psalm-immutable
 */
final class ConnectionConfiguration
{
    /**
     * @param ''|'s'|'ssc' $encryptionLevel
     */
    public function __construct(
        private readonly string $serverAgent,
        private readonly UriInterface $serverAddress,
        private readonly string $serverVersion,
        private readonly ConnectionProtocol $protocol,
        private readonly AccessMode $accessMode,
        private readonly ?DatabaseInfo $databaseInfo,
        private readonly string $encryptionLevel
    ) {}

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

    public function getDatabaseInfo(): ?DatabaseInfo
    {
        return $this->databaseInfo;
    }

    /**
     * @return ''|'s'|'ssc'
     */
    public function getEncryptionLevel(): string
    {
        return $this->encryptionLevel;
    }
}
