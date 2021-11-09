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
use function count;
use Exception;
use function is_array;
use function is_string;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Formatter\Specialised\BoltOGMTranslator;
use Laudis\Neo4j\Formatter\Specialised\HttpOGMArrayTranslator;
use Laudis\Neo4j\Formatter\Specialised\HttpOGMStringTranslator;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Formats the result in a basic OGM (Object Graph Mapping) format by mapping al cypher types to types found in the \Laudis\Neo4j\Types namespace.
 *
 * @see https://neo4j.com/docs/driver-manual/current/cypher-workflow/#driver-type-mapping
 *
 * @psalm-type OGMTypes = string|int|float|bool|null|\Laudis\Neo4j\Types\Date|\Laudis\Neo4j\Types\DateTime|\Laudis\Neo4j\Types\Duration|\Laudis\Neo4j\Types\LocalDateTime|\Laudis\Neo4j\Types\LocalTime|\Laudis\Neo4j\Types\Time|\Laudis\Neo4j\Types\CypherList|\Laudis\Neo4j\Types\CypherMap|\Laudis\Neo4j\Types\Node|\Laudis\Neo4j\Types\Relationship|\Laudis\Neo4j\Types\Path|\Laudis\Neo4j\Types\Cartesian3DPoint|\Laudis\Neo4j\Types\CartesianPoint|\Laudis\Neo4j\Types\WGS84Point|\Laudis\Neo4j\Types\WGS843DPoint
 * @implements FormatterInterface<CypherList<CypherMap<OGMTypes>>>
 *
 * @psalm-type OGMResults = CypherList<CypherMap<OGMTypes>>
 *
 * @psalm-import-type NodeArray from \Laudis\Neo4j\Formatter\Specialised\HttpOGMArrayTranslator
 * @psalm-import-type MetaArray from \Laudis\Neo4j\Formatter\Specialised\HttpOGMArrayTranslator
 * @psalm-import-type RelationshipArray from \Laudis\Neo4j\Formatter\Specialised\HttpOGMArrayTranslator
 *
 * @psalm-type CypherResultDataRow = list<array{row: list<scalar|array|null>, meta: MetaArray, graph: array{nodes: list<NodeArray>, relationships: list<RelationshipArray>}}>
 * @psalm-type CypherResult = array{columns: list<string>, data: CypherResultDataRow}
 *
 * @psalm-import-type BoltMeta from \Laudis\Neo4j\Contracts\FormatterInterface
 *
 * @psalm-immutable
 */
final class OGMFormatter implements FormatterInterface
{
    private BoltOGMTranslator $boltTranslator;
    private HttpOGMArrayTranslator $arrayTranslator;
    private HttpOGMStringTranslator $stringTranslator;

    public function __construct(BoltOGMTranslator $boltTranslator, HttpOGMArrayTranslator $arrayTranslator, HttpOGMStringTranslator $stringTranslator)
    {
        $this->boltTranslator = $boltTranslator;
        $this->arrayTranslator = $arrayTranslator;
        $this->stringTranslator = $stringTranslator;
    }

    /**
     * Creates a new instance of itself.
     *
     * @pure
     */
    public static function create(): OGMFormatter
    {
        return new self(
            new BoltOGMTranslator(),
            new HttpOGMArrayTranslator(),
            new HttpOGMStringTranslator()
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
            $meta = $data['meta'];
            $nodes = $data['graph']['nodes'];
            $relationship = $data['graph']['relationships'];
            $metaIndex = 0;
            $relationshipIndex = 0;

            /** @var array<string, OGMTypes> $record */
            $record = [];
            foreach ($data['row'] as $i => $value) {
                if (is_array($value)) {
                    $translation = $this->arrayTranslator->translate($meta, $relationship, $metaIndex, $relationshipIndex, $nodes, $value);

                    $relationshipIndex += $translation[1];
                    $metaIndex += $translation[0];
                    $record[$columns[$i]] = $translation[2];
                } elseif (is_string($value)) {
                    [$metaIncrement, $translation] = $this->stringTranslator->translate($metaIndex, $meta, $value);
                    $metaIndex += $metaIncrement;
                    $record[$columns[$i]] = $translation;
                } else {
                    $record[$columns[$i]] = $value;
                    ++$metaIndex;
                }
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
