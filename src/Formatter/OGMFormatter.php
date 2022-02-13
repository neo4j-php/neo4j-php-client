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

use Laudis\Neo4j\Bolt\BoltResult;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Formatter\Specialised\BoltOGMTranslator;
use Laudis\Neo4j\Formatter\Specialised\HttpOGMTranslator;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use stdClass;

/**
 * Formats the result in a basic OGM (Object Graph Mapping) format by mapping al cypher types to types found in the \Laudis\Neo4j\Types namespace.
 *
 * @see https://neo4j.com/docs/driver-manual/current/cypher-workflow/#driver-type-mapping
 *
 * @psalm-type OGMTypes = string|int|float|bool|null|\Laudis\Neo4j\Types\Date|\Laudis\Neo4j\Types\DateTime|\Laudis\Neo4j\Types\Duration|\Laudis\Neo4j\Types\LocalDateTime|\Laudis\Neo4j\Types\LocalTime|\Laudis\Neo4j\Types\Time|\Laudis\Neo4j\Types\CypherList|\Laudis\Neo4j\Types\CypherMap|\Laudis\Neo4j\Types\Node|\Laudis\Neo4j\Types\Relationship|\Laudis\Neo4j\Types\Path|\Laudis\Neo4j\Types\Cartesian3DPoint|\Laudis\Neo4j\Types\CartesianPoint|\Laudis\Neo4j\Types\WGS84Point|\Laudis\Neo4j\Types\WGS843DPoint
 *
 * @psalm-type OGMResults = CypherList<CypherMap<OGMTypes>>
 *
 * @psalm-import-type BoltMeta from \Laudis\Neo4j\Contracts\FormatterInterface
 *
 * @implements FormatterInterface<CypherList<CypherMap<OGMTypes>>>
 */
final class OGMFormatter implements FormatterInterface
{
    private BoltOGMTranslator $boltTranslator;
    private HttpOGMTranslator $httpTranslator;

    /**
     * @psalm-mutation-free
     */
    public function __construct(BoltOGMTranslator $boltTranslator, HttpOGMTranslator $httpTranslator)
    {
        $this->boltTranslator = $boltTranslator;
        $this->httpTranslator = $httpTranslator;
    }

    /**
     * Creates a new instance of itself.
     *
     * @pure
     */
    public static function create(): OGMFormatter
    {
        return new self(new BoltOGMTranslator(), new HttpOGMTranslator());
    }

    /**
     * @param BoltMeta $meta
     *
     * @return CypherList<CypherMap<OGMTypes>>
     */
    public function formatBoltResult(array $meta, BoltResult $result, ConnectionInterface $connection, float $runStart, float $resultAvailableAfter, Statement $statement): CypherList
    {
        return (new CypherList(function () use ($result, $meta) {
            foreach ($result as $row) {
                yield $this->formatRow($meta, $row);
            }
        }))->withCacheLimit($result->getFetchSize());
    }

    /**
     * @psalm-mutation-free
     */
    public function formatHttpResult(ResponseInterface $response, stdClass $body, ConnectionInterface $connection, float $resultsAvailableAfter, float $resultsConsumedAfter, iterable $statements): CypherList
    {
        /** @var list<CypherList<CypherMap<OGMTypes>>> $tbr */
        $tbr = [];

        /** @var list<stdClass> $results */
        $results = $body->results;
        foreach ($results as $result) {
            $tbr[] = $this->httpTranslator->translateResult($result);
        }

        return new CypherList($tbr);
    }

    /**
     * @param BoltMeta    $meta
     * @param list<mixed> $result
     *
     * @return CypherMap<OGMTypes>
     *
     * @psalm-mutation-free
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

    /**
     * @psalm-mutation-free
     */
    public function decorateRequest(RequestInterface $request): RequestInterface
    {
        return $request;
    }

    /**
     * @psalm-mutation-free
     */
    public function statementConfigOverride(): array
    {
        return [
            'resultDataContents' => ['ROW', 'GRAPH'],
        ];
    }
}
