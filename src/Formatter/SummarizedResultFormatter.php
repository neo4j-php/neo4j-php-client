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

use function in_array;
use function is_int;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\ResultSummary;
use Laudis\Neo4j\Databags\ServerInfo;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\SummaryCounters;
use Laudis\Neo4j\Enum\QueryTypeEnum;
use Laudis\Neo4j\Types\CypherList;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

/**
 * Decorates the result of the provided format with an extensive summary.
 *
 * @template T
 *
 * @psalm-import-type CypherResponseSet from \Laudis\Neo4j\Contracts\FormatterInterface
 * @psalm-import-type CypherResponse from \Laudis\Neo4j\Contracts\FormatterInterface
 * @psalm-import-type BoltCypherStats from \Laudis\Neo4j\Contracts\FormatterInterface
 * @psalm-import-type OGMResults from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @implements FormatterInterface<SummarizedResult<T>>
 *
 * @psalm-immutable
 */
final class SummarizedResultFormatter implements FormatterInterface
{
    /** @var FormatterInterface<T> */
    private FormatterInterface $formatter;

    /**
     * Creates a new instance of itself by decorating an OGMFormatter.
     *
     * @return self<OGMResults>
     *
     * @pure
     */
    public static function create(): self
    {
        return self::createWithFormatter(OGMFormatter::create());
    }

    /**
     * @template U
     *
     * Creates a new summarized result formatter by decorating the given formatter.
     *
     * @param FormatterInterface<U> $formatter
     *
     * @return self<U>
     *
     * @pure
     */
    public static function createWithFormatter(FormatterInterface $formatter): self
    {
        return new self($formatter);
    }

    /**
     * @param FormatterInterface<T> $formatter
     */
    public function __construct(FormatterInterface $formatter)
    {
        $this->formatter = $formatter;
    }

    /**
     * @param CypherResponse $response
     * @param T              $results
     *
     * @return SummarizedResult<T>
     */
    public function formatHttpStats(array $response, ConnectionInterface $connection, Statement $statement, float $resultAvailableAfter, float $resultConsumedAfter, $results): SummarizedResult
    {
        if (!isset($response['stats'])) {
            throw new UnexpectedValueException('No stats found in the response set');
        }

        $counters = new SummaryCounters(
            $response['stats']['nodes_created'] ?? 0,
            $response['stats']['nodes_deleted'] ?? 0,
            $response['stats']['relationships_created'] ?? 0,
            $response['stats']['relationships_deleted'] ?? 0,
            $response['stats']['properties_set'] ?? 0,
            $response['stats']['labels_added'] ?? 0,
            $response['stats']['labels_removed'] ?? 0,
            $response['stats']['indexes_added'] ?? 0,
            $response['stats']['indexes_removed'] ?? 0,
            $response['stats']['constraints_added'] ?? 0,
            $response['stats']['constraints_removed'] ?? 0,
            $response['stats']['contains_updates'] ?? false,
            $response['stats']['contains_system_updates'] ?? false,
            $response['stats']['system_updates'] ?? 0,
        );

        $summary = new ResultSummary(
            $counters,
            $connection->getDatabaseInfo(),
            new CypherList(),
            null,
            null,
            $statement,
            QueryTypeEnum::fromCounters($counters),
            $resultAvailableAfter,
            $resultConsumedAfter,
            new ServerInfo(
                $connection->getServerAddress(),
                $connection->getProtocol(),
                $connection->getServerAgent()
            )
        );

        return new SummarizedResult($results, $summary);
    }

    /**
     * @param array{stats?: BoltCypherStats} $response
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

    public function formatBoltResult(array $meta, array $results, ConnectionInterface $connection, float $resultAvailableAfter, float $resultConsumedAfter, Statement $statement): SummarizedResult
    {
        $last = array_key_last($results);
        if (!isset($results[$last])) {
            throw new UnexpectedValueException('Empty bolt result set');
        }

        /** @var array{stats?: BoltCypherStats} */
        $response = $results[$last];

        $counters = $this->formatBoltStats($response);
        $summary = new ResultSummary(
            $counters,
            $connection->getDatabaseInfo(),
            new CypherList(),
            null,
            null,
            $statement,
            QueryTypeEnum::fromCounters($counters),
            $resultAvailableAfter,
            $resultConsumedAfter,
            new ServerInfo(
                $connection->getServerAddress(),
                $connection->getProtocol(),
                $connection->getServerAgent()
            )
        );
        $formattedResult = $this->formatter->formatBoltResult($meta, $results, $connection, $resultAvailableAfter, $resultConsumedAfter, $statement);

        return new SummarizedResult($formattedResult, $summary);
    }

    public function formatHttpResult(ResponseInterface $response, array $body, ConnectionInterface $connection, float $resultsAvailableAfter, float $resultsConsumedAfter, iterable $statements): CypherList
    {
        /** @var list<SummarizedResult<T>> */
        $tbr = [];

        $toDecorate = $this->formatter->formatHttpResult($response, $body, $connection, $resultsAvailableAfter, $resultsConsumedAfter, $statements);
        $i = 0;
        foreach ($statements as $statement) {
            $result = $body['results'][$i];
            $tbr[] = $this->formatHttpStats($result, $connection, $statement, $resultsAvailableAfter, $resultsConsumedAfter, $toDecorate->get($i));
            ++$i;
        }

        return new CypherList($tbr);
    }

    public function decorateRequest(RequestInterface $request): RequestInterface
    {
        return $this->formatter->decorateRequest($request);
    }

    public function statementConfigOverride(): array
    {
        return array_merge($this->formatter->statementConfigOverride(), [
            'includeStats' => true,
        ]);
    }
}
