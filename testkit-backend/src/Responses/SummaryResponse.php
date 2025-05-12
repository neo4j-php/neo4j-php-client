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

namespace Laudis\Neo4j\TestkitBackend\Responses;

use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Responses\Types\CypherObject;
use stdClass;

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
                'counters' => $summary->getCounters()->toArray(),
                'database' => $summary->getDatabaseInfo()->getName(),
                'notifications' => $summary->getNotifications(),
                'plan' => $summary->getPlan(),
                'profile' => $summary->getProfiledPlan(),
                'query' => [
                    'text' => $summary->getStatement()->getText(),
                    'parameters' => $this->toCypherObjects($summary->getStatement()->getParameters()),
                ],
                'queryType' => $summary->getQueryType(),
                'resultAvailableAfter' => $summary->getResultAvailableAfter(),
                'resultConsumedAfter' => $summary->getResultConsumedAfter(),
                'serverInfo' => $summary->getServerInfo(),
            ],
        ];
    }

    private function toCypherObjects(iterable $toArray): array|stdClass
    {
        $cypherObjects = [];
        foreach ($toArray as $name => $value) {
            $cypherObjects[$name] = CypherObject::autoDetect($value);
        }

        if (count($cypherObjects) === 0) {
            return new stdClass();
        }

        return $cypherObjects;
    }
}
