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
use Laudis\Neo4j\Contracts\FormatterInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

/**
 * Request factory decorator to correctly configure a default Request.
 */
final class RequestFactory implements RequestFactoryInterface
{
    /** @readonly */
    private RequestFactoryInterface $requestFactory;
    /** @readonly */
    private AuthenticateInterface $authenticate;
    /** @readonly */
    private string $userAgent;
    /** @readonly */
    private UriInterface $authUri;
    /** @readonly */
    private ?FormatterInterface $formatter;

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        RequestFactoryInterface $requestFactory,
        AuthenticateInterface $authenticate,
        UriInterface $authUri,
        string $userAgent,
        FormatterInterface $formatter = null
    ) {
        $this->requestFactory = $requestFactory;
        $this->authenticate = $authenticate;
        $this->authUri = $authUri;
        $this->userAgent = $userAgent;
        $this->formatter = $formatter;
    }

    public function createRequest(string $method, $uri, bool $formatterSpecificAcceptHeader = false): RequestInterface
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

        if (
            $formatterSpecificAcceptHeader &&
            !is_null($this->formatter) &&
            method_exists($this->formatter, 'requiresJolt') &&
            $this->formatter->requiresJolt()
        ) {
            // TODO: throw an error if formatter requires Jolt and Neo4j version < 4.2.5
            // @see https://github.com/neo4j/neo4j/issues/12663

            $acceptHeader = 'application/vnd.neo4j.jolt+json-seq;strict=true;charset=UTF-8';
        } else {
            $acceptHeader = 'application/json;charset=UTF-8';
        }

        return $request->withHeader('Accept', $acceptHeader)
            ->withHeader('Content-Type', 'application/json');
    }
}
