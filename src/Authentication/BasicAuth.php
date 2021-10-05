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

use function base64_encode;
use Bolt\Bolt;
use Exception;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Authenticates connections using a basic username and password.
 */
final class BasicAuth implements AuthenticateInterface
{
    private string $username;
    private string $password;

    /**
     * @psalm-external-mutation-free
     */
    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

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
     * @throws Exception
     */
    public function authenticateBolt(Bolt $bolt, UriInterface $uri, string $userAgent): void
    {
        $bolt->init($userAgent, $this->username, $this->password);
    }

    /**
     * @psalm-mutation-free
     */
    public function extractFromUri(UriInterface $uri): AuthenticateInterface
    {
        return $this;
    }
}
