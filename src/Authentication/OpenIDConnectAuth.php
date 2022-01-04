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

use Bolt\helpers\Auth;
use Bolt\protocol\V3;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

final class OpenIDConnectAuth implements AuthenticateInterface
{
    private string $token;

    /**
     * @psalm-external-mutation-free
     */
    public function __construct(string $token)
    {
        $this->token = $token;
    }

    /**
     * @psalm-mutation-free
     */
    public function authenticateHttp(RequestInterface $request, UriInterface $uri, string $userAgent): RequestInterface
    {
        /**
         * @psalm-suppress ImpureMethodCall Request is a pure object:
         *
         * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message-meta.md#why-value-objects
         */
        return $request->withHeader('Authorization', 'Bearer '.$this->token)
            ->withHeader('User-Agent', $userAgent);
    }

    public function authenticateBolt(V3 $bolt, string $userAgent): array
    {
        /** @var array{server: string, connection_id: string, hints: list} */
        return $bolt->hello(Auth::bearer($this->token));
    }
}
