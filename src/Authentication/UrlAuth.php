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

/**
 * Authenticates connections based on the information found in the Uri.
 */
final class UrlAuth implements AuthenticateInterface
{
    /**
     * @psalm-mutation-free
     */
    public function authenticateHttp(RequestInterface $request, UriInterface $uri, string $userAgent): RequestInterface
    {
        /**
         * @psalm-suppress ImpureMethodCall Uri is a pure object:
         *
         * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message-meta.md#why-value-objects
         */
        $userInfo = $uri->getUserInfo();

        if (substr_count($userInfo, ':') === 1) {
            [$user, $pass] = explode(':', $userInfo);

            return Authenticate::basic($user, $pass)
                ->authenticateHttp($request, $uri, $userAgent);
        }

        return Authenticate::disabled()->authenticateHttp($request, $uri, $userAgent);
    }

    public function authenticateBolt(Bolt $bolt, UriInterface $uri, string $userAgent): void
    {
        $this->extractFromUri($uri)->authenticateBolt($bolt, $uri, $userAgent);
    }

    /**
     * @psalm-mutation-free
     */
    public function extractFromUri(UriInterface $uri): AuthenticateInterface
    {
        /**
         * @psalm-suppress ImpureMethodCall Uri is a pure object:
         *
         * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message-meta.md#why-value-objects
         */
        $userInfo = $uri->getUserInfo();

        if (substr_count($userInfo, ':') === 1) {
            [$user, $pass] = explode(':', $userInfo);

            return Authenticate::basic($user, $pass);
        }

        return Authenticate::disabled();
    }
}
