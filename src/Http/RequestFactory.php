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

namespace Laudis\Neo4j\Http;

use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Request factory decorator to correctly configure a default Request.
 */
final class RequestFactory implements RequestFactoryInterface
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(
        /** @readonly */
        private readonly RequestFactoryInterface $requestFactory,
        /** @readonly */
        private readonly AuthenticateInterface $authenticate,
        /** @readonly */
        private readonly UriInterface $authUri,
        /** @readonly */
        private readonly string $userAgent
    ) {}

    public function createRequest(string $method, $uri): RequestInterface
    {
        $request = $this->requestFactory->createRequest($method, $uri);
        $request = $this->authenticate->authenticateHttp($request, $this->authUri, $this->userAgent);
        $uri = $request->getUri()->withUserInfo('');
        $port = $uri->getPort();
        if ($port === null) {
            $port = $uri->getScheme() === 'https' ? 7473 : 7474;
            $uri = $uri->withPort($port);
        }

        return $request
            ->withUri($uri)
            ->withHeader('Accept', 'application/json;charset=UTF-8')
            ->withHeader('Content-Type', 'application/json');
    }
}
