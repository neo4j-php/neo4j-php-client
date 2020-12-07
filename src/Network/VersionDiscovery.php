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

use JsonException;
use Laudis\Neo4j\Network\Http\HttpInjections;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;

/**
 * @psalm-type DiscoveryResult = array{
 *      bolt_routing:string,
 *      transaction: string,
 *      bolt_direct: string,
 *      neo4j_version: string,
 *      neo4j_edition: string
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
    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    public function discoverTransactionUrl(string $url, HttpInjections $injections): string
    {
        $discovery = $this->discovery($injections->client(), $injections->requestFactory(), $url);
        $version = $discovery['neo4j_version'] ?? null;

        if ($version === null) {
            $discovery = $this->discovery($injections->client(), $injections->requestFactory(), $url.'/db/data');
        }
        $tsx = $discovery['transaction'];

        return str_replace('{databaseName}', $injections->database(), $tsx);
    }

    /**
     * @throws ClientExceptionInterface
     * @throws JsonException
     *
     * @return DiscoveryResult|DiscoveryResultLegacy $discovery
     */
    private function discovery(ClientInterface $client, RequestFactoryInterface $factory, string $uri): array
    {
        $response = $client->sendRequest($factory->createRequest('GET', $uri));

        /** @var DiscoveryResultLegacy|DiscoveryResult $result */
        $result = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);

        return $result;
    }
}
