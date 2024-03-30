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

use function base64_encode;

use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use Exception;
use Laudis\Neo4j\Common\ResponseHelper;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Authenticates connections using a basic username and password.
 */
final class BasicAuth implements AuthenticateInterface
{
    /**
     * @psalm-external-mutation-free
     */
    public function __construct(
        private readonly string $username,
        private readonly string $password
    ) {}

    /**
     * @psalm-mutation-free
     */
    public function authenticateHttp(RequestInterface $request, UriInterface $uri, string $userAgent): RequestInterface
    {
        $combo = base64_encode($this->username.':'.$this->password);

        /**
         * @psalm-suppress ImpureMethodCall Request is a pure object:
         *
         * @see https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-7-http-message-meta.md#why-value-objects
         */
        return $request->withHeader('Authorization', 'Basic '.$combo)
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
                'scheme' => 'basic',
                'principal' => $this->username,
                'credentials' => $this->password,
            ]);
            ResponseHelper::getResponse($protocol);
            return $response->content;
        } else {
            $protocol->hello([
                'user_agent' => $userAgent,
                'scheme' => 'basic',
                'principal' => $this->username,
                'credentials' => $this->password,
            ]);
            return ResponseHelper::getResponse($protocol)->content;
        }
    }

    public function toString(UriInterface $uri): string
    {
        return sprintf('Basic %s:%s@%s:%s', $this->username, '######', $uri->getHost(), $uri->getPort() ?? '');
    }
}
