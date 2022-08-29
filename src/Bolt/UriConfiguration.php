<?php

declare(strict_types=1);

namespace Laudis\Neo4j\Bolt;

final class UriConfiguration
{
    private string $host;
    private ?int $port;
    private string $sslLevel;
    private array $sslConfiguration;
    private ?float $timeout;

    public function __construct(string $host, ?int $port, string $sslLevel, array $sslConfiguration, ?float $timeout)
    {
        $this->host = $host;
        $this->port = $port;
        $this->sslLevel = $sslLevel;
        $this->sslConfiguration = $sslConfiguration;
        $this->timeout = $timeout;
    }

    public function getHost(): string
    {
        return $this->host;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * @return 's'|'ssc'|''
     */
    public function getSslLevel(): string
    {
        return $this->sslLevel;
    }

    public function getSslConfiguration(): array
    {
        return $this->sslConfiguration;
    }

    public function getTimeout(): ?float
    {
        return $this->timeout;
    }
}
