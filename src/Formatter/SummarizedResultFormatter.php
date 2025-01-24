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

use function in_array;
use function is_int;

use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\BoltResult;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\ResultSummary;
use Laudis\Neo4j\Databags\ServerInfo;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\SummaryCounters;
use Laudis\Neo4j\Enum\QueryTypeEnum;
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

use function microtime;

/**
 * Decorates the result of the provided format with an extensive summary.
 *
 * @psalm-type OGMTypes = string|int|float|bool|null|Date|DateTime|Duration|LocalDateTime|LocalTime|Time|Node|Relationship|Path|Cartesian3DPoint|CartesianPoint|WGS84Point|WGS843DPoint|DateTimeZoneId|CypherList<mixed>|CypherMap<mixed>
 * @psalm-type OGMResults = CypherList<CypherMap<OGMTypes>>
 * @psalm-type CypherStats = array{
 *     nodes_created: int,
 *     nodes_deleted: int,
 *     relationships_created: int,
 *     relationships_deleted: int,
 *     properties_set: int,
 *     labels_added: int,
 *     labels_removed: int,
 *     indexes_added: int,
 *     indexes_removed: int,
 *     constraints_added: int,
 *     constraints_removed: int,
 *     contains_updates: bool,
 *     contains_system_updates?: bool,
 *     system_updates?: int
 * }
 * @psalm-type BoltCypherStats = array{
 *     nodes-created?: int,
 *     nodes-deleted?: int,
 *     relationships-created?: int,
 *     relationships-deleted?: int,
 *     properties-set?: int,
 *     labels-added?: int,
 *     labels-removed?: int,
 *     indexes-added?: int,
 *     indexes-removed?: int,
 *     constraints-added?: int,
 *     constraints-removed?: int,
 *     contains-updates?: bool,
 *     contains-system-updates?: bool,
 *     system-updates?: int,
 *     db?: string
 * }
 * @psalm-type CypherError = array{code: string, message: string}
 * @psalm-type CypherRowResponse = array{row: list<scalar|null|array<array-key,scalar|null|array>>}
 * @psalm-type CypherResponse = array{columns:list<string>, data:list<CypherRowResponse>, stats?:CypherStats}
 * @psalm-type CypherResponseSet = array{results: list<CypherResponse>, errors: list<CypherError>}
 * @psalm-type BoltMeta = array{t_first: int, fields: list<string>, qid ?: int}
 */
final class SummarizedResultFormatter
{
    /**
     * @pure
     */
    public static function create(): self
    {
        return new self(new BoltOGMTranslator());
    }

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly BoltOGMTranslator $translator
    ) {}

    /**
     * @param array{stats?: BoltCypherStats}&array $response
     *
     * @psalm-mutation-free
     */
    public function formatBoltStats(array $response): SummaryCounters
    {
        $stats = $response['stats'] ?? false;
        if ($stats === false) {
            return new SummaryCounters();
        }

        $updateCount = 0;
        foreach ($stats as $key => $value) {
            if (is_int($value) && !in_array($key, ['system-updates', 'contains-system-updates'])) {
                $updateCount += $value;
            }
        }

        return new SummaryCounters(
            $stats['nodes-created'] ?? 0,
            $stats['nodes-deleted'] ?? 0,
            $stats['relationships-created'] ?? 0,
            $stats['relationships-deleted'] ?? 0,
            $stats['properties-set'] ?? 0,
            $stats['labels-added'] ?? 0,
            $stats['labels-removed'] ?? 0,
            $stats['indexes-added'] ?? 0,
            $stats['indexes-removed'] ?? 0,
            $stats['constraints-added'] ?? 0,
            $stats['constraints-removed'] ?? 0,
            $updateCount > 0,
            ($stats['contains-system-updates'] ?? $stats['system-updates'] ?? 0) >= 1,
            $stats['system-updates'] ?? 0
        );
    }

    public function formatBoltResult(array $meta, BoltResult $result, BoltConnection $connection, float $runStart, float $resultAvailableAfter, Statement $statement, BookmarkHolder $holder): SummarizedResult
    {
        /** @var ResultSummary|null $summary */
        $summary = null;
        $result->addFinishedCallback(
            /** @param {array{stats?: BoltCypherStats}&array} $response */
            function (mixed $response) use ($connection, $statement, $runStart, $resultAvailableAfter, &$summary) {
                $stats = $this->formatBoltStats($response);
                $resultConsumedAfter = microtime(true) - $runStart;
                $db = $response['db'] ?? '';
                $summary = new ResultSummary(
                    $stats,
                    new DatabaseInfo($db),
                    new CypherList(),
                    null,
                    null,
                    $statement,
                    QueryTypeEnum::fromCounters($stats),
                    $resultAvailableAfter,
                    $resultConsumedAfter,
                    new ServerInfo(
                        $connection->getServerAddress(),
                        $connection->getProtocol(),
                        $connection->getServerAgent()
                    )
                );
            });

        $formattedResult = $this->processBoltResult($meta, $result, $connection, $runStart, $resultAvailableAfter, $statement, $holder);

        /**
         * @psalm-suppress MixedArgument
         *
         * @var SummarizedResult<CypherMap<OGMTypes>>
         */
        return (new SummarizedResult($summary, $formattedResult))->withCacheLimit($result->getFetchSize());
    }

    /**
     * @param BoltMeta $meta
     *
     * @return CypherList<CypherMap<OGMTypes>>
     */
    private function processBoltResult(array $meta, BoltResult $result, BoltConnection $connection, float $runStart, float $resultAvailableAfter, Statement $statement, BookmarkHolder $holder): CypherList
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
     * @psalm-mutation-free
     */
    private function formatRow(array $meta, array $result): CypherMap
    {
        /** @var array<string, OGMTypes> $map */
        $map = [];
        if (!array_key_exists('fields', $meta)) {
            return new CypherMap($map);
        }

        foreach ($meta['fields'] as $i => $column) {
            $map[$column] = $this->translator->mapValueToType($result[$i]);
        }

        return new CypherMap($map);
    }
}
