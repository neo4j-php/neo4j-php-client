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

namespace Laudis\Neo4j\Network;

use Ds\Vector;
use JsonException;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

/**
 * @psalm-type DiscoveryResult = array{
 *      bolt_routing:string,
 *      transaction: string,
 *      bolt_direct: string,
 *      neo4j_version: string,
 *      neo4j_edition: string,
 *      db/cluster?: string,
 *      dbms/cluster?: string,
 *      data?: string
 * }
 * @psalm-type DiscoveryResultLegacy = array{
 *     extensions: array,
 *     node: string,
 *     relationship: string,
 *     node_index: string,
 *     relationship_index: string,
 *     extensions_info: string,
 *     relationship_types: string,
 *     batch: string,
 *     cypher: string,
 *     indexed: string,
 *     constraints: string,
 *     transaction: string,
 *     node_labels: string,
 *     neo4j_version: string
 * }
 */
final class VersionDiscovery
{
    private ClientInterface $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function discoverTransactionUrl(RequestInterface $request, string $database): string
    {
        $discovery = $this->discovery($request);
        $version = $discovery['neo4j_version'] ?? null;

        if ($version === null) {
            $request = $request->withUri($request->getUri()->withPath($discovery['data'] ?? '/db/data'));
            $discovery = $this->discovery($request);
        }
        $tsx = $discovery['transaction'];

        return str_replace('{databaseName}', $database, $tsx);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     *
     * @return DiscoveryResult|DiscoveryResultLegacy
     */
    private function discovery(RequestInterface $request): array
    {
        $response = $this->client->sendRequest($request);

        $contents = $response->getBody()->getContents();
        if ($response->getStatusCode() >= 400) {
            throw new Neo4jException(new Vector([new Neo4jError((string) $response->getStatusCode(), $contents)]));
        }

        /** @var DiscoveryResultLegacy|DiscoveryResult */
        return json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    }
}
