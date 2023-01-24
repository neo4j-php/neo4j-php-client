<?php

declare(strict_types=1);

namespace Laudis\Neo4j\TestkitBackend\Requests;

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

final class NewDriverRequest
{
    public function __construct(private string $uri, private AuthorizationTokenRequest $authToken, private ?string $userAgent = null, private ?bool $resolverRegistered = null, private ?bool $domainNameResolverRegistered = null, private ?int $connectionTimeoutMs = null)
    {
    }

    public function getUri(): string
    {
        return $this->uri;
    }

    public function getAuthToken(): AuthorizationTokenRequest
    {
        return $this->authToken;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function isResolverRegistered(): ?bool
    {
        return $this->resolverRegistered;
    }

    public function isDomainNameResolverRegistered(): ?bool
    {
        return $this->domainNameResolverRegistered;
    }

    public function getConnectionTimeoutMs(): ?int
    {
        return $this->connectionTimeoutMs;
    }
}
