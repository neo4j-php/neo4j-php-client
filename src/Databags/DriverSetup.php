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

use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\UriInterface;

/**
 * Basic object containing all the information needed to setup a driver.
 *
 * @psalm-immutable
 */
final class DriverSetup
{
    private UriInterface $uri;
    private AuthenticateInterface $auth;

    public function __construct(UriInterface $uri, AuthenticateInterface $auth)
    {
        $this->uri = $uri;
        $this->auth = $auth;
    }

    public function getAuth(): AuthenticateInterface
    {
        return $this->auth;
    }

    public function getUri(): UriInterface
    {
        return $this->uri;
    }
}
