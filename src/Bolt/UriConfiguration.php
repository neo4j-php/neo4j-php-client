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

final class UriConfiguration
{
    private string $host;
    private ?int $port;
    /** @var ''|'s'|'ssc' */
    private string $sslLevel;
    private array $sslConfiguration;
    private ?float $timeout;

    /**
     * @param string $host
     * @param int|null $port
     * @param ''|'s'|'ssc' $sslLevel
     * @param array $sslConfiguration
     * @param float|null $timeout
     */
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
