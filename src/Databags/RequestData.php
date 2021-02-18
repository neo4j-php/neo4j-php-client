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

    public function __construct(string $transactionEndpoint, string $user, string $password)
    {
        $this->endpoint = $transactionEndpoint;
        $this->user = $user;
        $this->password = $password;
    }

    public function withEndpoint(string $tsx): RequestData
    {
        return new RequestData($tsx, $this->user, $this->password);
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
}
