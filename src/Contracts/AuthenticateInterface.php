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

namespace Laudis\Neo4j\Contracts;

use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

interface AuthenticateInterface
{
    /**
     * Authenticates a RequestInterface with the provided configuration Uri and userAgent.
     */
    public function authenticateHttp(RequestInterface $request, UriInterface $uri, string $userAgent): RequestInterface;

    /**
     * Authenticates a Bolt connection with the provided configuration Uri and userAgent.
     *
     * @return array{server: string, connection_id: string, hints: list}
     */
    public function authenticateBolt(V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol, string $userAgent): array;

    /**
     * Returns a string representation of the authentication.
     */
    public function toString(UriInterface $uri): string;
}
