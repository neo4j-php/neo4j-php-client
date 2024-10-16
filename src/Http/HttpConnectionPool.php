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

use Generator;

use function json_encode;

use Laudis\Neo4j\Common\ConnectionConfiguration;
use Laudis\Neo4j\Common\Resolvable;
use Laudis\Neo4j\Common\Uri;
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
use Throwable;

/**
 * @implements ConnectionPoolInterface<HttpConnection>
 */
final class HttpConnectionPool implements ConnectionPoolInterface
{
    /**
     * @param Resolvable<ClientInterface>        $client
     * @param Resolvable<RequestFactory>         $requestFactory
     * @param Resolvable<StreamFactoryInterface> $streamFactory
     * @param Resolvable<string>                 $tsxUrl
     *
     * @psalm-mutation-free
     */
    public function __construct(
        /**
         * @psalm-readonly
         */
        private readonly Resolvable $client,
        /**
         * @psalm-readonly
         */
        private readonly Resolvable $requestFactory,
        /**
         * @psalm-readonly
         */
        private readonly Resolvable $streamFactory,
        private readonly AuthenticateInterface $auth,
        private readonly string $userAgent,
        private readonly Resolvable $tsxUrl
    ) {}

    public function acquire(SessionConfiguration $config): Generator
    {
        yield 0.0;

        $uri = Uri::create($this->tsxUrl->resolve());
        $request = $this->requestFactory->resolve()->createRequest('POST', $uri);

        $path = $request->getUri()->getPath().'/commit';
        $uri = $uri->withPath($path);
        $request = $request->withUri($uri);

        $body = json_encode([
            'statements' => [
                [
                    'statement' => <<<'CYPHER'
CALL dbms.components()
YIELD name, versions, edition
RETURN name, versions, edition
CYPHER
    ,
                ],
            ],
            'resultDataContents' => [],
            'includeStats' => false,
        ], JSON_THROW_ON_ERROR);

        $request = $request->withBody($this->streamFactory->resolve()->createStream($body));

        $response = $this->client->resolve()->sendRequest($request);
        $data = HttpHelper::interpretResponse($response);
        /** @var array{0: array{name: string, versions: list<string>, edition: string}} $results */
        $results = (new BasicFormatter())->formatHttpResult($response, $data, null)->first();

        $version = $results[0]['versions'][0] ?? '';

        $config = new ConnectionConfiguration(
            $results[0]['name'].'-'.$results[0]['edition'].'/'.$version,
            $uri,
            $version,
            ConnectionProtocol::HTTP(),
            $config->getAccessMode(),
            new DatabaseInfo($config->getDatabase() ?? ''),
            ''
        );

        return new HttpConnection($this->client->resolve(), $config, $this->auth, $this->userAgent);
    }

    public function canConnect(UriInterface $uri, AuthenticateInterface $authenticate, ?string $userAgent = null): bool
    {
        $request = $this->requestFactory->resolve()->createRequest('GET', $uri);
        $client = $this->client->resolve();

        try {
            return $client->sendRequest($request)->getStatusCode() === 200;
        } catch (Throwable) {
            return false;
        }
    }

    public function release(ConnectionInterface $connection): void
    {
        // Nothing to release in the current HTTP Protocol implementation
    }

    public function close(): void
    {
        // Nothing to close in the current HTTP Protocol implementation
    }
}
