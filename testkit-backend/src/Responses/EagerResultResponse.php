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

use Laudis\Neo4j\Databags\EagerResult;
use Laudis\Neo4j\Databags\ResultSummary;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Responses\Types\CypherObject;
use Laudis\Neo4j\Types\CypherMap;
use stdClass;
use Symfony\Component\Uid\Uuid;

/**
 * Response for ExecuteQuery containing an eager result.
 */
final class EagerResultResponse implements TestkitResponseInterface
{
    /** @var list<string> */
    private array $keys;

    /** @var list<array{values: list<array<string, mixed>>}> */
    private array $records;

    /** @var array<string, mixed> */
    private array $summary;

    public function __construct(Uuid $id, EagerResult|SummarizedResult $eagerResult)
    {
        unset($id);

        if ($eagerResult instanceof EagerResult) {
            $this->keys = $eagerResult->keys();
            $recordMaps = $eagerResult->records();
            $summary = $eagerResult->getSummary();
        } else {
            $eagerResult->list();
            $this->keys = $eagerResult->keys();
            $recordMaps = $eagerResult->list();
            $summary = $eagerResult->getSummary();
        }

        $this->records = [];
        foreach ($recordMaps as $record) {
            $values = [];
            foreach ($record->values() as $value) {
                $values[] = CypherObject::autoDetect($value)->jsonSerialize();
            }
            $this->records[] = ['values' => $values];
        }

        $this->summary = $this->serializeSummary($summary);
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'EagerResult',
            'data' => [
                'keys' => $this->keys,
                'records' => $this->records,
                'summary' => $this->summary,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeSummary(ResultSummary $summary): array
    {
        return [
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
        ];
    }

    /**
     * @param iterable<string, mixed>|CypherMap<mixed> $parameters
     *
     * @return array<string, mixed>|stdClass
     */
    private function toCypherObjects(iterable $parameters): array|stdClass
    {
        $cypherObjects = [];
        foreach ($parameters as $name => $value) {
            $cypherObjects[$name] = CypherObject::autoDetect($value);
        }

        if (count($cypherObjects) === 0) {
            return new stdClass();
        }

        return $cypherObjects;
    }
}
