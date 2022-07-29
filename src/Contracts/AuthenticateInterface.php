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

use Bolt\protocol\AProtocol;
use Bolt\protocol\V3;
use Bolt\protocol\V4;
use Bolt\protocol\V4_1;
use Bolt\protocol\V4_2;
use Bolt\protocol\V4_3;
use Bolt\protocol\V4_4;
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
     * @param V3|V4|V4_1|V4_2|V4_3|V4_4 $protocol
     *
     * @return array{server: string, connection_id: string, hints: list}
     */
    public function authenticateBolt(AProtocol $protocol, string $userAgent): array;
}
