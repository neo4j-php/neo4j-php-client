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
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Common\ResponseHelper;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Log\LogLevel;

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
        private readonly string $password,
        private readonly ?Neo4jLogger $logger,
    ) {}

    public function authenticateHttp(RequestInterface $request, UriInterface $uri, string $userAgent): RequestInterface
    {
        $this->logger?->log(LogLevel::DEBUG, 'Authenticating using BasicAuth');
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
     * @throws Exception
     *
     * @return array{server: string, connection_id: string, hints: list}
     */
    public function authenticateBolt(V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol, string $userAgent): array
    {
        if (method_exists($protocol, 'logon')) {
            $this->logger?->log(LogLevel::DEBUG, 'HELLO', ['user_agent' => $userAgent]);
            $protocol->hello(['user_agent' => $userAgent]);
            $response = ResponseHelper::getResponse($protocol);
            $this->logger?->log(LogLevel::DEBUG, 'LOGON', ['scheme' => 'basic', 'principal' => $this->username]);
            $protocol->logon([
                'scheme' => 'basic',
                'principal' => $this->username,
                'credentials' => $this->password,
            ]);
            ResponseHelper::getResponse($protocol);

            /** @var array{server: string, connection_id: string, hints: list} */
            return $response->content;
        } else {
            $this->logger?->log(LogLevel::DEBUG, 'HELLO', ['user_agent' => $userAgent, 'scheme' => 'basic', 'principal' => $this->username]);
            $protocol->hello([
                'user_agent' => $userAgent,
                'scheme' => 'basic',
                'principal' => $this->username,
                'credentials' => $this->password,
            ]);

            /** @var array{server: string, connection_id: string, hints: list} */
            return ResponseHelper::getResponse($protocol)->content;
        }
    }

    public function toString(UriInterface $uri): string
    {
        return sprintf('Basic %s:%s@%s:%s', $this->username, '######', $uri->getHost(), $uri->getPort() ?? '');
    }
}
