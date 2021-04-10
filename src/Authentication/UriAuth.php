<?php

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
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\RequestInterface;

final class UriAuth implements AuthenticateInterface
{
    public function authenticateHttp(RequestInterface $request, array $parsedUrl): RequestInterface
    {
        if (isset($parsedUrl['user'], $parsedUrl['pass'])) {
            return Authenticate::basic($parsedUrl['user'], $parsedUrl['pass'])
                ->authenticateHttp($request, $parsedUrl);
        }

        return Authenticate::disabled()->authenticateHttp($request, $parsedUrl);
    }

    public function authenticateBolt(Bolt $bolt, array $parsedUrl, string $userAgent): void
    {
        if (isset($parsedUrl['user'], $parsedUrl['pass'])) {
            Authenticate::basic($parsedUrl['user'], $parsedUrl['pass'])->authenticateBolt($bolt, $parsedUrl, $userAgent);
        } else {
            Authenticate::disabled()->authenticateBolt($bolt, $parsedUrl, $userAgent);
        }
    }
}
