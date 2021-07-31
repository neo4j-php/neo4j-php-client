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

namespace Laudis\Neo4j\TestkitBackend\Responses;

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;

/**
 * Represents summary when consuming a result.
 */
final class SummaryResponse implements TestkitResponseInterface
{
    private SummaryCountersResponse $statistics;
    private string $database;
    /** @var mixed */
    private $notifications;
    private string $plan;
    private string $profile;
    private SummaryQueryResponse $summaryQuery;
    private string $queryType;
    private int $resultAvailableAfter;
    private int $resultConsumedAfter;
    private ServerInfoResponse $serverInfo;

    /**
     * SummaryResponse constructor.
     *
     * @param mixed $notifications
     *
     * TODO - Figure out type of notifications variable
     */
    public function __construct(
        SummaryCountersResponse $statistics,
        string $database,
        $notifications,
        string $plan,
        string $profile,
        SummaryQueryResponse $summaryQuery,
        string $queryType,
        int $resultAvailableAfter,
        int $resultConsumedAfter,
        ServerInfoResponse $serverInfo
    ) {
        $this->statistics = $statistics;
        $this->database = $database;
        $this->notifications = $notifications;
        $this->plan = $plan;
        $this->profile = $profile;
        $this->summaryQuery = $summaryQuery;
        $this->queryType = $queryType;
        $this->resultAvailableAfter = $resultAvailableAfter;
        $this->resultConsumedAfter = $resultConsumedAfter;
        $this->serverInfo = $serverInfo;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'Summary',
            'data' => [
                'counters' => $this->statistics,
                'database' => $this->database,
                'notifications' => $this->notifications,
                'plan' => $this->plan,
                'profile' => $this->profile,
                'query' => $this->summaryQuery,
                'query_type' => $this->queryType,
                'result_available_after' => $this->resultAvailableAfter,
                'result_consumed_after' => $this->resultConsumedAfter,
                'server_info' => $this->serverInfo,
            ],
        ];
    }
}
