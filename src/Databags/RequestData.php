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

namespace Laudis\Neo4j\Databags;

/**
 * @psalm-immutable
 */
final class RequestData
{
    private string $endpoint;
    private string $user;
    private string $password;
    private bool $includeStats;

    public function __construct(string $transactionEndpoint, string $user, string $password, bool $includeStats)
    {
        $this->endpoint = $transactionEndpoint;
        $this->user = $user;
        $this->password = $password;
        $this->includeStats = $includeStats;
    }

    public function withEndpoint(string $tsx): RequestData
    {
        return new RequestData($tsx, $this->user, $this->password, $this->includeStats);
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function includeStats(): bool
    {
        return $this->includeStats;
    }
}
