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

namespace Laudis\Neo4j\HttpDriver;

use JsonException;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\RequestData;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Formatter\HttpCypherFormatter;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class RequestFactory
{
    private RequestFactoryInterface $factory;
    private StreamFactoryInterface $streamFactory;
    private HttpCypherFormatter $formatter;

    public function __construct(RequestFactoryInterface $factory, StreamFactoryInterface $streamFactory, HttpCypherFormatter $formatter)
    {
        $this->factory = $factory;
        $this->streamFactory = $streamFactory;
        $this->formatter = $formatter;
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @throws JsonException
     */
    public function openTransaction(RequestData $data, iterable $statements): RequestInterface
    {
        $body = $this->formatter->prepareBody($statements, $data);

        $request = $this->createRequest($data, 'POST');
        $request->getBody()->write($body);

        return $request;
    }

    public function createRequest(RequestData $data, string $method, string $body = ''): RequestInterface
    {
        $combo = base64_encode($data->getUser().':'.$data->getPassword());

        return $this->factory->createRequest($method, $data->getEndpoint())
            ->withBody($this->streamFactory->createStream($body))
            ->withHeader('Accept', 'application/json;charset=UTF-8')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('User-Agent', 'LaudisNeo4j/'.ClientInterface::VERSION)
            ->withHeader('Authorization', 'Basic '.$combo);
    }

    /**
     * @param iterable<Statement> $statements
     *
     * @throws JsonException
     */
    public function post(RequestData $data, iterable $statements): RequestInterface
    {
        $body = $this->formatter->prepareBody($statements, $data);

        return $this->createRequest($data, 'POST', $body);
    }

    public function delete(RequestData $data): RequestInterface
    {
        return $this->createRequest($data, 'DELETE');
    }
}
