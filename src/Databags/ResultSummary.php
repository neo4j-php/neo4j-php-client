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
use Laudis\Neo4j\Types\AbstractCypherContainer;
use Laudis\Neo4j\Types\CypherList;

final class ResultSummary extends AbstractCypherContainer
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

    public function getCounters(): SummaryCounters
    {
        return $this->counters;
    }

    public function getDatabaseInfo(): DatabaseInfo
    {
        return $this->databaseInfo;
    }

    /**
     * @return CypherList<Notification>
     */
    public function getNotifications(): CypherList
    {
        return $this->notifications;
    }

    public function getPlan(): ?Plan
    {
        return $this->plan;
    }

    public function getProfiledPlan(): ?ProfiledPlan
    {
        return $this->profiledPlan;
    }

    public function getStatement(): Statement
    {
        return $this->statement;
    }

    public function getQueryType(): QueryTypeEnum
    {
        return $this->queryType;
    }

    public function getResultAvailableAfter(): float
    {
        return $this->resultAvailableAfter;
    }

    public function getResultConsumedAfter(): float
    {
        return $this->resultConsumedAfter;
    }

    public function getServerInfo(): ServerInfo
    {
        return $this->serverInfo;
    }

    public function getIterator()
    {
        yield 'counters' => $this->counters;
        yield 'databaseInfo' => $this->databaseInfo;
        yield 'notifications' => $this->notifications;
        yield 'plan' => $this->plan;
        yield 'profiledPlan' => $this->profiledPlan;
        yield 'statement' => $this->statement;
        yield 'queryType' => $this->queryType;
        yield 'resultAvailableAfter' => $this->resultAvailableAfter;
        yield 'resultConsumedAfter' => $this->resultConsumedAfter;
        yield 'serverInfo' => $this->serverInfo;
    }
}
