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
 * Basic object containing all the information needed to setup a driver.
 *
 * @psalm-immutable
 */
final class DriverSetup
{
    public function __construct(
        private readonly UriInterface $uri,
        private readonly AuthenticateInterface $auth
    ) {}

    public function getAuth(): AuthenticateInterface
    {
        return $this->auth;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }
}
