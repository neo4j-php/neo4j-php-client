<?php

declare(strict_types=1);

namespace Laudis\Neo4j\TestkitBackend\Input;

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

final class NewDriverInput
{
    private string $uri;
    private string $authToken;
    private string $userAgent;
    private bool $resolverRegistered;
    private bool $domainNameResolverRegistered;
    private ?int $connectionTimeoutMs;

    public function __construct(
        string $uri,
        string $authToken,
        string $userAgent,
        bool $resolverRegistered,
        bool $domainNameResolverRegistered,
        ?int $connectionTimeoutMs
    ) {
        $this->uri = $uri;
        $this->authToken = $authToken;
        $this->userAgent = $userAgent;
        $this->resolverRegistered = $resolverRegistered;
        $this->domainNameResolverRegistered = $domainNameResolverRegistered;
        $this->connectionTimeoutMs = $connectionTimeoutMs;
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getAuthToken(): string
    {
        return $this->authToken;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function isResolverRegistered(): bool
    {
        return $this->resolverRegistered;
    }

    public function isDomainNameResolverRegistered(): bool
    {
        return $this->domainNameResolverRegistered;
    }

    public function getConnectionTimeoutMs(): ?int
    {
        return $this->connectionTimeoutMs;
    }
}
