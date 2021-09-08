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

namespace Laudis\Neo4j\Formatter;

use function array_slice;
use ArrayIterator;
use function count;
use Exception;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Formatter\Specialised\BoltOGMTranslator;
use Laudis\Neo4j\Formatter\Specialised\HttpOGMArrayTranslator;
use Laudis\Neo4j\Formatter\Specialised\HttpOGMStringTranslator;
use Laudis\Neo4j\Formatter\Specialised\HttpOGMTranslator;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @see https://neo4j.com/docs/driver-manual/current/cypher-workflow/#driver-type-mapping
 *
 * @psalm-type OGMTypes = string|\Laudis\Neo4j\Types\Date|\Laudis\Neo4j\Types\DateTime|\Laudis\Neo4j\Types\Duration|\Laudis\Neo4j\Types\LocalDateTime|\Laudis\Neo4j\Types\LocalTime|\Laudis\Neo4j\Types\Time|int|float|bool|null|\Laudis\Neo4j\Types\CypherList|\Laudis\Neo4j\Types\CypherMap|\Laudis\Neo4j\Types\Node|\Laudis\Neo4j\Types\Relationship|\Laudis\Neo4j\Types\Path|\Laudis\Neo4j\Types\Cartesian3DPoint|\Laudis\Neo4j\Types\CartesianPoint|\Laudis\Neo4j\Types\WGS84Point|\Laudis\Neo4j\Types\WGS843DPoint
 * @implements FormatterInterface<CypherList<CypherMap<OGMTypes>>>
 *
 * @psalm-type OGMResults = CypherList<CypherMap<OGMTypes>>
 *
 * @psalm-import-type NodeArray from \Laudis\Neo4j\Formatter\Specialised\HttpOGMArrayTranslator
 * @psalm-import-type RelationshipArray from \Laudis\Neo4j\Formatter\Specialised\HttpOGMArrayTranslator
 *
 * @psalm-type CypherResult = array{columns: list<string>, data: list<array{row: list<scalar|array|null>, meta: array, graph: array{nodes: list<NodeArray>, relationships: list<RelationshipArray>}}>}
 *
 * @psalm-import-type BoltMeta from \Laudis\Neo4j\Contracts\FormatterInterface
 */
final class OGMFormatter implements FormatterInterface
{
    private BoltOGMTranslator $boltTranslator;
    private HttpOGMTranslator $httpTranslator;

    public function __construct(BoltOGMTranslator $boltTranslator, HttpOGMTranslator $httpTranslator)
    {
        $this->boltTranslator = $boltTranslator;
        $this->httpTranslator = $httpTranslator;
    }

    /**
     * @psalm-mutation-free
     */
    public static function create(): OGMFormatter
    {
        return new self(
            new BoltOGMTranslator(),
            new HttpOGMTranslator(
                new HttpOGMArrayTranslator(),
                new HttpOGMStringTranslator()
            )
        );
    }

    /**
     * @param BoltMeta $meta
     *
     * @return CypherList<CypherMap<OGMTypes>>
     */
    public function formatBoltResult(array $meta, array $results, ConnectionInterface $connection, float $resultAvailableAfter, float $resultConsumedAfter, Statement $statement): CypherList
    {
        /** @var list<list<mixed>> $results */
        $results = array_slice($results, 0, count($results) - 1);

        /** @var list<CypherMap<OGMTypes>> $tbr */
        $tbr = [];

        foreach ($results as $result) {
            $tbr[] = $this->formatRow($meta, $result);
        }

        return new CypherList($tbr);
    }

    /**
     * @throws Exception
     */
    public function formatHttpResult(ResponseInterface $response, array $body, ConnectionInterface $connection, float $resultsAvailableAfter, float $resultsConsumedAfter, iterable $statements): CypherList
    {
        /** @var list<CypherList<CypherMap<OGMTypes>>> $tbr */
        $tbr = [];

        foreach ($body['results'] as $results) {
            /** @var CypherResult $results */
            $tbr[] = $this->buildResult($results);
        }

        return new CypherList($tbr);
    }

    /**
     * @param CypherResult $result
     *
     * @throws Exception
     *
     * @return CypherList<CypherMap<OGMTypes>>
     */
    private function buildResult(array $result): CypherList
    {
        /** @var list<CypherMap<OGMTypes>> $tbr */
        $tbr = [];

        $columns = $result['columns'];
        foreach ($result['data'] as $data) {
            $meta = new ArrayIterator($data['meta']);
            $nodes = $data['graph']['nodes'];
            $relationship = new ArrayIterator($data['graph']['relationships']);

            /** @var array<string, OGMTypes> $record */
            $record = [];
            foreach ($data['row'] as $i => $value) {
                $record[$columns[$i]] = $this->httpTranslator->translate($meta, $relationship, $nodes, $value);
            }

            $tbr[] = new CypherMap($record);
        }

        return new CypherList($tbr);
    }

    /**
     * @param BoltMeta    $meta
     * @param list<mixed> $result
     *
     * @return CypherMap<OGMTypes>
     */
    private function formatRow(array $meta, array $result): CypherMap
    {
        /** @var array<string, OGMTypes> $map */
        $map = [];
        foreach ($meta['fields'] as $i => $column) {
            $map[$column] = $this->boltTranslator->mapValueToType($result[$i]);
        }

        return new CypherMap($map);
    }

    public function decorateRequest(RequestInterface $request): RequestInterface
    {
        return $request;
    }

    public function statementConfigOverride(): array
    {
        return [
            'resultDataContents' => ['ROW', 'GRAPH'],
        ];
    }
}
