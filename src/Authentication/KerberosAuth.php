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

use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use Laudis\Neo4j\Common\ResponseHelper;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Exception;

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
        private readonly string $token
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

    /**
     * @return array{server: string, connection_id: string, hints: array}
     * @throws Exception
     */
    public function authenticateBolt(V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol, string $userAgent): array
    {
        if (method_exists($protocol, 'logon')) {
            $protocol->hello(['user_agent' => $userAgent]);
            $response = ResponseHelper::getResponse($protocol);
            $protocol->logon([
                'scheme' => 'kerberos',
                'principal' => '',
                'credentials' => $this->token,
            ]);
            ResponseHelper::getResponse($protocol);
            return $response->content;
        } else {
            $protocol->hello([
                'user_agent' => $userAgent,
                'scheme' => 'kerberos',
                'principal' => '',
                'credentials' => $this->token,
            ]);
            return ResponseHelper::getResponse($protocol)->content;
        }
    }

    public function toString(UriInterface $uri): string
    {
        return sprintf('Kerberos %s@%s:%s', $this->token, $uri->getHost(), $uri->getPort() ?? '');
    }
}
