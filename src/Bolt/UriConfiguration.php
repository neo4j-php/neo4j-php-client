<?php

declare(strict_types=1);

namespace Laudis\Neo4j\Bolt;

final class UriConfiguration
{
    private string $host;
    private int $port;
    private string $sslLevel;
    private array $sslConfiguration;
    private int $timeout;

    public function __construct(string $host, int $port, string $sslLevel, array $sslConfiguration, int $timeout)
    {
        $this->host = $host;
        $this->port = $port;
        $this->sslLevel = $sslLevel;
        $this->sslConfiguration = $sslConfiguration;
        $this->timeout = $timeout;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @return string
     */
    public function getSslLevel(): string
    {
        return $this->sslLevel;
    }

    /**
     * @return array
     */
    public function getSslConfiguration(): array
    {
        return $this->sslConfiguration;
    }

    /**
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }
}
