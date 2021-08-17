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

use Laudis\Neo4j\Databags\SummaryCounters;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;

/**
 * Represents the counters info included in the Summary response.
 */
final class SummaryCountersResponse implements TestkitResponseInterface
{
    private SummaryCounters $statistics;

    public function __construct(SummaryCounters $statistics)
    {
        $this->statistics = $statistics;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'SummaryCounters',
            'data' => [
                'constraints_added' => $this->statistics->constraintsAdded(),
                'constraints_removed' => $this->statistics->constraintsRemoved(),
                'contains_system_updates' => $this->statistics->containsSystemUpdates(),
                'contains_updates' => $this->statistics->containsUpdates(),
                'indexes_added' => $this->statistics->indexesAdded(),
                'indexes_removed' => $this->statistics->indexesRemoved(),
                'labels_added' => $this->statistics->labelsAdded(),
                'labels_removed' => $this->statistics->labelsRemoved(),
                'nodes_created' => $this->statistics->nodesCreated(),
                'nodes_deleted' => $this->statistics->nodesDeleted(),
                'properties_set' => $this->statistics->propertiesSet(),
                'relationships_created' => $this->statistics->relationshipsCreated(),
                'relationships_deleted' => $this->statistics->relationshipsDeleted(),
                'system_updates' => $this->statistics->systemUpdates(),
            ],
        ];
    }
}
