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

namespace Laudis\Neo4j\Contracts;

use Bolt\Bolt;
use Exception;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

interface AuthenticateInterface
{
    /**
     * @psalm-mutation-free
     *
     * Authenticates a RequestInterface with the provided configuration Uri and userAgent.
     */
    public function authenticateHttp(RequestInterface $request, UriInterface $uri, string $userAgent): RequestInterface;

    /**
     * Authenticates a Bolt connection with the provided configuration Uri and userAgent.
     *
     * @throws Exception
     */
    public function authenticateBolt(Bolt $bolt, UriInterface $uri, string $userAgent): void;

    /**
     * @psalm-mutation-free
     *
     * Extracts the Uri login information and returns an authentication strategy not requiring the url anymore.
     */
    public function extractFromUri(UriInterface $uri): self;
}
