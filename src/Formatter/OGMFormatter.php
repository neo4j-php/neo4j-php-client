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
use Bolt\Bolt;
use function count;
use Ds\Map;
use Ds\Vector;
use Exception;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Formatter\Specialised\BoltOGMTranslator;
use Laudis\Neo4j\Formatter\Specialised\HttpOGMTranslator;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @see https://neo4j.com/docs/driver-manual/current/cypher-workflow/#driver-type-mapping
 *
 * @psalm-type OGMTypes = scalar|null|\Laudis\Neo4j\Types\CypherList|\Laudis\Neo4j\Types\CypherMap|\Laudis\Neo4j\Types\Date|\Laudis\Neo4j\Types\DateTime|\Laudis\Neo4j\Types\Duration|\Laudis\Neo4j\Types\Node|\Laudis\Neo4j\Types\Relationship|\Laudis\Neo4j\Types\Path|\Laudis\Neo4j\Types\LocalDateTime|\Laudis\Neo4j\Types\LocalTime
 * @implements FormatterInterface<CypherList<CypherMap<OGMTypes>>>
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
     * @return CypherList<CypherMap<OGMTypes>>
     */
    public function formatBoltResult(array $meta, array $results, Bolt $bolt): CypherList
    {
        $results = array_slice($results, 0, count($results) - 1);

        /** @var Vector<CypherMap<OGMTypes>> $tbr */
        $tbr = new Vector();

        foreach ($results as $result) {
            $tbr->push($this->formatRow($meta, $result));
        }

        return new CypherList($tbr);
    }

    /**
     * @throws Exception
     */
    public function formatHttpResult(ResponseInterface $response, array $body): Vector
    {
        $tbr = new Vector();

        foreach ($body['results'] as $results) {
            $tbr->push($this->buildResult($results));
        }

        return $tbr;
    }

    /**
     * @throws Exception
     */
    private function buildResult(array $result): Vector
    {
        $tbr = new Vector();

        /** @psalm-suppress PossiblyUndefinedStringArrayOffset */
        $columns = $result['columns'];
        /** @psalm-suppress PossiblyUndefinedStringArrayOffset */
        foreach ($result['data'] as $data) {
            $meta = new ArrayIterator($data['meta']);
            $nodes = $data['graph']['nodes'];
            $relationship = new ArrayIterator($data['graph']['relationships']);

            $record = new Map();
            foreach ($data['row'] as $i => $value) {
                $record->put($columns[$i], $this->httpTranslator->translate($meta, $relationship, $nodes, $value));
            }

            $tbr->push($record);
        }

        return $tbr;
    }

    /**
     * @return CypherMap<OGMTypes>
     */
    private function formatRow(array $meta, array $result): CypherMap
    {
        /** @var Map<string, OGMTypes> $map */
        $map = new Map();
        /** @psalm-suppress PossiblyUndefinedStringArrayOffset */
        foreach ($meta['fields'] as $i => $column) {
            $map->put($column, $this->boltTranslator->mapValueToType($result[$i]));
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
