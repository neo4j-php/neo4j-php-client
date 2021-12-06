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

namespace Laudis\Neo4j\TestkitBackend\Requests;

final class AuthorizationTokenRequest
{
    private string $scheme;
    private string $principal;
    private string $credentials;
    private string $realm;
    private string $ticket;

    public function __construct(
        string $scheme,
        string $principal,
        string $credentials,
        string $realm = null,
        string $ticket = null
    ) {
        $this->scheme = $scheme;
        $this->principal = $principal;
        $this->credentials = $credentials;
        $this->realm = $realm ?? '';
        $this->ticket = $ticket ?? '';
    }

    public function getScheme(): string
    {
        return $this->scheme;
    }

    public function getPrincipal(): string
    {
        return $this->principal;
    }

    public function getCredentials(): string
    {
        return $this->credentials;
    }

    public function getRealm(): string
    {
        return $this->realm;
    }

    public function getTicket(): string
    {
        return $this->ticket;
    }
}
