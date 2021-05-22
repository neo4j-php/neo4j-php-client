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

namespace Laudis\Neo4j\Authentication;

use Bolt\Bolt;
use function explode;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use function substr_count;

final class UrlAuth implements AuthenticateInterface
{
    public function authenticateHttp(RequestInterface $request, UriInterface $uri, string $userAgent): RequestInterface
    {
        if (substr_count($uri->getUserInfo(), ':') === 1) {
            [$user, $pass] = explode(':', $uri->getUserInfo());

            return Authenticate::basic($user, $pass)
                ->authenticateHttp($request, $uri, $userAgent);
        }

        return Authenticate::disabled()->authenticateHttp($request, $uri, $userAgent);
    }

    public function authenticateBolt(Bolt $bolt, UriInterface $uri, string $userAgent): void
    {
        if (substr_count($uri->getUserInfo(), ':') === 1) {
            [$user, $pass] = explode(':', $uri->getUserInfo());
            Authenticate::basic($user, $pass)->authenticateBolt($bolt, $uri, $userAgent);
        } else {
            Authenticate::disabled()->authenticateBolt($bolt, $uri, $userAgent);
        }
    }
}
