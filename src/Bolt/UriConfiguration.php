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
    /**
     * @param ''|'s'|'ssc' $sslLevel
     */
    public function __construct(
        private readonly string $host,
        private readonly ?int $port,
        private readonly string $sslLevel,
        private readonly array $sslConfiguration,
        private readonly ?float $timeout
    ) {}

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
