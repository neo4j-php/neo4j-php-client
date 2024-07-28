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

namespace Laudis\Neo4j\Databags;

use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\UriInterface;

/**
 * @internal
 */
final class ConnectionRequestData
{
    public function __construct(
        private readonly string $hostname,
        private readonly UriInterface $uri,
        private readonly AuthenticateInterface $auth,
        private readonly string $userAgent,
        private readonly SslConfiguration $config
    ) {}

    public function getHostname(): string
    {
        return $this->hostname;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }

    public function getAuth(): AuthenticateInterface
    {
        return $this->auth;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function getSslConfig(): SslConfiguration
    {
        return $this->config;
    }
}
