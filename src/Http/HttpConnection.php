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

namespace Laudis\Neo4j\Http;

use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\UriInterface;

/**
 * @implements ConnectionInterface<ClientInterface>
 */
final class HttpConnection implements ConnectionInterface
{
    /** @psalm-readonly */
    private ClientInterface $client;
    /** @psalm-readonly */
    private ConnectionConfiguration $config;

    private bool $isOpen = true;

    /**
     * @psalm-mutation-free
     */
    public function __construct(ClientInterface $client, ConnectionConfiguration $config)
    {
        $this->client = $client;
        $this->config = $config;
    }

    /**
     * @psalm-mutation-free
     */
    public function getImplementation(): ClientInterface
    {
        return $this->client;
    }

    /**
     * @psalm-mutation-free
     */
    public function getServerAgent(): string
    {
        return $this->config->getServerAgent();
    }

    /**
     * @psalm-mutation-free
     */
    public function getServerAddress(): UriInterface
    {
        return $this->config->getServerAddress();
    }

    /**
     * @psalm-mutation-free
     */
    public function getServerVersion(): string
    {
        return $this->config->getServerVersion();
    }

    /**
     * @psalm-mutation-free
     */
    public function getProtocol(): ConnectionProtocol
    {
        return $this->config->getProtocol();
    }

    /**
     * @psalm-mutation-free
     */
    public function getAccessMode(): AccessMode
    {
        return $this->config->getAccessMode();
    }

    /**
     * @psalm-mutation-free
     */
    public function getDatabaseInfo(): DatabaseInfo
    {
        return $this->config->getDatabaseInfo() ?? new DatabaseInfo('');
    }

    /**
     * @psalm-mutation-free
     */
    public function isOpen(): bool
    {
        return $this->isOpen;
    }

    /**
     * @psalm-external-mutation-free
     */
    public function open(): void
    {
        $this->isOpen = true;
    }

    /**
     * @psalm-external-mutation-free
     */
    public function close(): void
    {
        $this->isOpen = false;
    }

    public function reset(): void
    {
        // Cannot reset a stateless protocol
    }

    public function setTimeout(float $timeout): void
    {
        // Impossible to actually set a timeout with PSR definition
    }
}
