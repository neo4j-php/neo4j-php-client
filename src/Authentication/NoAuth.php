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

namespace Laudis\Neo4j\Authentication;

use Bolt\helpers\Auth;
use Bolt\protocol\Response;
use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Exception\Neo4jException;
use Psr\Http\Message\RequestInterface;
use Stringable;

/**
 * Doesn't authenticate connections.
 *
 * @internal
 */
final class NoAuth implements AuthenticateInterface, Stringable
{
    /**
     * @psalm-mutation-free
     */
    public function authenticateHttp(RequestInterface $request, string $userAgent): RequestInterface
    {
        /**
         * @psalm-suppress ImpureMethodCall Request is a pure object:
         *
         * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message-meta.md#why-value-objects
         */
        return $request->withHeader('User-Agent', $userAgent);
    }

    public function authenticateBolt(V4_4|V5|V5_1|V5_2|V5_3 $bolt, string $userAgent): array
    {
        $response = $bolt->hello(Auth::none($userAgent));
        if ($response->getSignature() === Response::SIGNATURE_FAILURE) {
            throw Neo4jException::fromBoltResponse($response);
        }

        /** @var array{server: string, connection_id: string, hints: list} */
        return $response->getContent();
    }

    public function __toString(): string
    {
        return 'No Auth';
    }
}
