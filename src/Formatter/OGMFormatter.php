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

namespace Laudis\Neo4j\Formatter;

use function array_key_exists;

use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\BoltResult;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Formatter\Specialised\BoltOGMTranslator;
use Laudis\Neo4j\Types\Cartesian3DPoint;
use Laudis\Neo4j\Types\CartesianPoint;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\DateTimeZoneId;
use Laudis\Neo4j\Types\Duration;
use Laudis\Neo4j\Types\LocalDateTime;
use Laudis\Neo4j\Types\LocalTime;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Path;
use Laudis\Neo4j\Types\Relationship;
use Laudis\Neo4j\Types\Time;
use Laudis\Neo4j\Types\WGS843DPoint;
use Laudis\Neo4j\Types\WGS84Point;

/**
 * Formats the result in a basic OGM (Object Graph Mapping) format by mapping al cypher types to types found in the \Laudis\Neo4j\Types namespace.
 *
 * @see https://neo4j.com/docs/driver-manual/current/cypher-workflow/#driver-type-mapping
 *
 * @psalm-type OGMTypes = string|int|float|bool|null|Date|DateTime|Duration|LocalDateTime|LocalTime|Time|Node|Relationship|Path|Cartesian3DPoint|CartesianPoint|WGS84Point|WGS843DPoint|DateTimeZoneId|CypherList<mixed>|CypherMap<mixed>
 * @psalm-type OGMResults = CypherList<CypherMap<OGMTypes>>
 *
 * @psalm-import-type BoltMeta from FormatterInterface
 *
 * @implements FormatterInterface<CypherList<CypherMap<OGMTypes>>>
 *
 * @deprecated Next major version will only use SummarizedResultFormatter
 */
final class OGMFormatter implements FormatterInterface
{
    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly BoltOGMTranslator $boltTranslator,
    ) {
    }

    /**
     * Creates a new instance of itself.
     *
     * @pure
     */
    public static function create(): OGMFormatter
    {
        return new self(new BoltOGMTranslator());
    }

    /**
     * @param BoltMeta $meta
     *
     * @return CypherList<CypherMap<OGMTypes>>
     */
    public function formatBoltResult(array $meta, BoltResult $result, BoltConnection $connection, float $runStart, float $resultAvailableAfter, Statement $statement, BookmarkHolder $holder): CypherList
    {
        $tbr = (new CypherList(function () use ($result, $meta) {
            foreach ($result as $row) {
                yield $this->formatRow($meta, $row);
            }
        }))->withCacheLimit($result->getFetchSize());

        $connection->subscribeResult($tbr);
        $result->addFinishedCallback(function (array $response) use ($holder) {
            if (array_key_exists('bookmark', $response) && is_string($response['bookmark'])) {
                $holder->setBookmark(new Bookmark([$response['bookmark']]));
            }
        });

        return $tbr;
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
}
