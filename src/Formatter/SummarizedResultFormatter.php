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
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Databags\ResultSummary;
use Laudis\Neo4j\Databags\ServerInfo;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\SummaryCounters;
use Laudis\Neo4j\Enum\QueryTypeEnum;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;

use function microtime;

/**
 * Decorates the result of the provided format with an extensive summary.
 *
 * @psalm-import-type CypherResponseSet from \Laudis\Neo4j\Contracts\FormatterInterface
 * @psalm-import-type CypherResponse from \Laudis\Neo4j\Contracts\FormatterInterface
 * @psalm-import-type BoltCypherStats from \Laudis\Neo4j\Contracts\FormatterInterface
 * @psalm-import-type OGMResults from \Laudis\Neo4j\Formatter\OGMFormatter
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @implements FormatterInterface<SummarizedResult<CypherMap<OGMTypes>>>
 */
final class SummarizedResultFormatter implements FormatterInterface
{
    /**
     * @pure
     */
    public static function create(): self
    {
        return new self(OGMFormatter::create());
    }

    /**
     * @psalm-mutation-free
     */
    public function __construct(
        private readonly OGMFormatter $formatter
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
        $result->addFinishedCallback(function (array $response) use ($connection, $statement, $runStart, $resultAvailableAfter, &$summary) {
            /** @var BoltCypherStats $response */
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

        $formattedResult = $this->formatter->formatBoltResult($meta, $result, $connection, $runStart, $resultAvailableAfter, $statement, $holder);

        /**
         * @psalm-suppress MixedArgument
         *
         * @var SummarizedResult<CypherMap<OGMTypes>>
         */
        return (new SummarizedResult($summary, $formattedResult))->withCacheLimit($result->getFetchSize());
    }
}
