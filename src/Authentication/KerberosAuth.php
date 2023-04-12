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
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Exception\Neo4jException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

use function sprintf;

/**
 * Authenticates connections using a kerberos token.
 */
final class KerberosAuth implements AuthenticateInterface
{
    /**
     * @psalm-external-mutation-free
     */
    public function __construct(
        private string $token
    ) {}

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
        return $request->withHeader('Authorization', 'Kerberos '.$this->token)
            ->withHeader('User-Agent', $userAgent);
    }

    public function authenticateBolt(V4_4|V5 $bolt, string $userAgent): array
    {
        $response = $bolt->hello(Auth::kerberos($this->token, $userAgent));
        if ($response->getSignature() === Response::SIGNATURE_FAILURE) {
            throw Neo4jException::fromBoltResponse($response);
        }

        /** @var array{server: string, connection_id: string, hints: list} */
        return $response->getContent();
    }

    public function toString(UriInterface $uri): string
    {
        return sprintf('Kerberos %s@%s:%s', $this->token, $uri->getHost(), $uri->getPort() ?? '');
    }
}
