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

use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;

/**
 * Represents summary when consuming a result.
 */
final class SummaryResponse implements TestkitResponseInterface
{
    private SummarizedResult $result;

    public function __construct(SummarizedResult $result)
    {
        $this->result = $result;
    }

    public function jsonSerialize(): array
    {
        $summary = $this->result->getSummary();

        return [
            'name' => 'Summary',
            'data' => [
                'counters' => new SummaryCountersResponse($summary->getCounters()),
                'database' => $summary->getDatabaseInfo()->getName(),
                'notifications' => $summary->getNotifications(),
                'plan' => $summary->getPlan(),
                'profile' => $summary->getProfiledPlan(),
                'query' => new SummaryQueryResponse($summary->getStatement()),
                'query_type' => $summary->getQueryType(),
                'result_available_after' => $summary->getResultAvailableAfter(),
                'result_consumed_after' => $summary->getResultConsumedAfter(),
                'server_info' => $summary->getServerInfo(),
            ],
        ];
    }
}
