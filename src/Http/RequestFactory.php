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

namespace Laudis\Neo4j\Http;

use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

final class RequestFactory implements RequestFactoryInterface
{
    private RequestFactoryInterface $requestFactory;
    private AuthenticateInterface $authenticate;
    private string $userAgent;
    private UriInterface $authUri;

    public function __construct(
        RequestFactoryInterface $requestFactory,
        AuthenticateInterface $authenticate,
        UriInterface $authUri,
        string $userAgent
    ) {
        $this->requestFactory = $requestFactory;
        $this->authenticate = $authenticate;
        $this->authUri = $authUri;
        $this->userAgent = $userAgent;
    }

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
        $request = $request->withUri($uri);

        return $request->withHeader('Accept', 'application/json;charset=UTF-8')
            ->withHeader('Content-Type', 'application/json');
    }
}
