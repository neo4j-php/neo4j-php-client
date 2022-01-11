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

namespace Laudis\Neo4j\Databags;

use Laudis\Neo4j\Enum\QueryTypeEnum;
use Laudis\Neo4j\Types\AbstractCypherObject;
use Laudis\Neo4j\Types\CypherList;

/**
 * The result summary of running a query.
 *
 * The result summary interface can be used to investigate details about the result:
 * - type of query run
 * - how many and which kinds of updates have been executed
 * - the query plan and profiling information if available
 * - timing information
 * - information about connection environment
 *
 * @psalm-immutable
 */
final class ResultSummary extends AbstractCypherObject
{
    private SummaryCounters $counters;
    private DatabaseInfo $databaseInfo;
    /** @var CypherList<Notification> */
    private CypherList $notifications;
    private ?Plan $plan;
    private ?ProfiledPlan $profiledPlan;
    private Statement $statement;
    private QueryTypeEnum $queryType;
    private float $resultAvailableAfter;
    private float $resultConsumedAfter;
    private ServerInfo $serverInfo;

    /**
     * @param CypherList<Notification> $notifications
     */
    public function __construct(
        SummaryCounters $counters,
        DatabaseInfo $databaseInfo,
        CypherList $notifications,
        ?Plan $plan,
        ?ProfiledPlan $profiledPlan,
        Statement $statement,
        QueryTypeEnum $queryType,
        float $resultAvailableAfter,
        float $resultConsumedAfter,
        ServerInfo $serverInfo
    ) {
        $this->counters = $counters;
        $this->databaseInfo = $databaseInfo;
        $this->notifications = $notifications;
        $this->plan = $plan;
        $this->profiledPlan = $profiledPlan;
        $this->statement = $statement;
        $this->queryType = $queryType;
        $this->resultAvailableAfter = $resultAvailableAfter;
        $this->resultConsumedAfter = $resultConsumedAfter;
        $this->serverInfo = $serverInfo;
    }

    /**
     * The counters for amount of operations the query triggered.
     */
    public function getCounters(): SummaryCounters
    {
        return $this->counters;
    }

    /**
     * The basic information of the database where the result is obtained from.
     */
    public function getDatabaseInfo(): DatabaseInfo
    {
        return $this->databaseInfo;
    }

    /**
     * A list of notifications that might arise when executing the query.
     *
     * @return CypherList<Notification>
     */
    public function getNotifications(): CypherList
    {
        return $this->notifications;
    }

    /**
     * This describes how the database will execute your query.
     */
    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    /**
     * This describes how the database executed your query.
     */
    public function getProfiledPlan(): ?ProfiledPlan
    {
        return $this->profiledPlan;
    }

    /**
     * The Statement that has been executed.
     */
    public function getStatement(): Statement
    {
        return $this->statement;
    }

    /**
     * The type of query that has been executed.
     */
    public function getQueryType(): QueryTypeEnum
    {
        return $this->queryType;
    }

    /**
     * The time it took the server to make the result available for consumption.
     */
    public function getResultAvailableAfter(): float
    {
        return $this->resultAvailableAfter;
    }

    /**
     * The time it took the server to consume the result.
     */
    public function getResultConsumedAfter(): float
    {
        return $this->resultConsumedAfter;
    }

    /**
     * The basic information of the server where the result is obtained from.
     */
    public function getServerInfo(): ServerInfo
    {
        return $this->serverInfo;
    }

    public function toArray(): array
    {
        return [
            'counters' => $this->counters,
            'databaseInfo' => $this->databaseInfo,
            'notifications' => $this->notifications,
            'plan' => $this->plan,
            'profiledPlan' => $this->profiledPlan,
            'statement' => $this->statement,
            'queryType' => $this->queryType,
            'resultAvailableAfter' => $this->resultAvailableAfter,
            'resultConsumedAfter' => $this->resultConsumedAfter,
            'serverInfo' => $this->serverInfo,
        ];
    }
}
