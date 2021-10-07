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

use function json_encode;
use Laudis\Neo4j\Common\Resolvable;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Formatter\BasicFormatter;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;

/**
 * @implements ConnectionPoolInterface<ClientInterface>
 */
final class HttpConnectionPool implements ConnectionPoolInterface
{
    /**
     * @var Resolvable<ClientInterface>
     * @psalm-readonly
     */
    private Resolvable $client;
    /**
     * @var Resolvable<RequestFactory>
     * @psalm-readonly
     */
    private Resolvable $requestFactory;
    /**
     * @var Resolvable<StreamFactoryInterface>
     * @psalm-readonly
     */
    private Resolvable $streamFactory;

    /**
     * @param Resolvable<StreamFactoryInterface> $streamFactory
     * @param Resolvable<RequestFactory>         $requestFactory
     * @param Resolvable<ClientInterface>        $client
     * @psalm-mutation-free
     */
    public function __construct(Resolvable $client, Resolvable $requestFactory, Resolvable $streamFactory)
    {
        $this->client = $client;
        $this->requestFactory = $requestFactory;
        $this->streamFactory = $streamFactory;
    }

    public function acquire(
        UriInterface $uri,
        AuthenticateInterface $authenticate,
        float $socketTimeout,
        string $userAgent,
        SessionConfiguration $config
    ): ConnectionInterface {
        $request = $this->requestFactory->resolve()->createRequest('POST', $uri);

        $path = $request->getUri()->getPath().'/commit';
        $uri = $request->getUri()->withPath($path);
        $request = $request->withUri($uri);

        $body = json_encode([
            'statements' => [
                [
                    'statement' => <<<'CYPHER'
CALL dbms.components()
YIELD name, versions, edition
UNWIND versions AS version
RETURN name, version, edition
CYPHER
                ],
            ],
            'resultDataContents' => [],
            'includeStats' => false,
        ], JSON_THROW_ON_ERROR);

        $request = $request->withBody($this->streamFactory->resolve()->createStream($body));

        $response = $this->client->resolve()->sendRequest($request);
        $data = HttpHelper::interpretResponse($response);
        /** @var array{0: array{name: string, version: string, edition: string}} $results */
        $results = (new BasicFormatter())->formatHttpResult($response, $data, null)->first();

        return new HttpConnection(
            $this->client->resolve(),
            $results[0]['name'].'-'.$results[0]['edition'].'/'.$results[0]['version'],
            $uri,
            $results[0]['version'],
            ConnectionProtocol::HTTP(),
            $config->getAccessMode(),
            new DatabaseInfo($config->getDatabase())
        );
    }
}
