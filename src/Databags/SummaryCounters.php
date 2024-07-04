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

namespace Laudis\Neo4j\Databags;

use Laudis\Neo4j\Types\AbstractCypherObject;

/**
 * Contains counters for various operations that a query triggered.
 *
 * @psalm-immutable
 *
 * @extends AbstractCypherObject<string, int|bool>
 */
final class SummaryCounters extends AbstractCypherObject
{
    public function __construct(
        private readonly int $nodesCreated = 0,
        private readonly int $nodesDeleted = 0,
        private readonly int $relationshipsCreated = 0,
        private readonly int $relationshipsDeleted = 0,
        private readonly int $propertiesSet = 0,
        private readonly int $labelsAdded = 0,
        private readonly int $labelsRemoved = 0,
        private readonly int $indexesAdded = 0,
        private readonly int $indexesRemoved = 0,
        private readonly int $constraintsAdded = 0,
        private readonly int $constraintsRemoved = 0,
        private readonly bool $containsUpdates = false,
        private readonly bool $containsSystemUpdates = false,
        private readonly int $systemUpdates = 0
    ) {}

    /**
     * Whether the query contained any updates.
     */
    public function containsUpdates(): bool
    {
        return $this->containsUpdates;
    }

    /**
     * The number of nodes created.
     */
    public function nodesCreated(): int
    {
        return $this->nodesCreated;
    }

    /**
     * The number of nodes deleted.
     */
    public function nodesDeleted(): int
    {
        return $this->nodesDeleted;
    }

    /**
     * The number of relationships created.
     */
    public function relationshipsCreated(): int
    {
        return $this->relationshipsCreated;
    }

    /**
     * The number of relationships deleted.
     */
    public function relationshipsDeleted(): int
    {
        return $this->relationshipsDeleted;
    }

    /**
     * The number of properties (on both nodes and relationships) set.
     */
    public function propertiesSet(): int
    {
        return $this->propertiesSet;
    }

    /**
     * The number of labels added to nodes.
     */
    public function labelsAdded(): int
    {
        return $this->labelsAdded;
    }

    /**
     * The number of labels removed from nodes.
     */
    public function labelsRemoved(): int
    {
        return $this->labelsRemoved;
    }

    /**
     * The number of indexes added to the schema.
     */
    public function indexesAdded(): int
    {
        return $this->indexesAdded;
    }

    /**
     * The number of indexed removed from the schema.
     */
    public function indexesRemoved(): int
    {
        return $this->labelsRemoved;
    }

    /**
     * The number of constraints added to the schema.
     */
    public function constraintsAdded(): int
    {
        return $this->constraintsAdded;
    }

    /**
     * The number of constraints removed from the schema.
     */
    public function constraintsRemoved(): int
    {
        return $this->constraintsRemoved;
    }

    /**
     * Returns whether the query updated the system graph in any way.
     */
    public function containsSystemUpdates(): bool
    {
        return $this->containsSystemUpdates;
    }

    /**
     * The number of system updates performed by this query.
     */
    public function systemUpdates(): int
    {
        return $this->systemUpdates;
    }

    /**
     * Creates a new SummaryCounter by merging this one with the provided result stats.
     * The integer results will be added together and the boolean results will be merged using OR.
     */
    public function merge(SummaryCounters $resultStats): SummaryCounters
    {
        return new SummaryCounters(
            $this->nodesCreated + $resultStats->nodesCreated,
            $this->nodesDeleted + $resultStats->nodesDeleted,
            $this->relationshipsCreated + $resultStats->relationshipsCreated,
            $this->relationshipsDeleted + $resultStats->relationshipsDeleted,
            $this->propertiesSet + $resultStats->propertiesSet,
            $this->labelsAdded + $resultStats->labelsAdded,
            $this->labelsRemoved + $resultStats->labelsRemoved,
            $this->indexesAdded + $resultStats->indexesAdded,
            $this->indexesRemoved + $resultStats->indexesRemoved,
            $this->constraintsAdded + $resultStats->constraintsAdded,
            $this->constraintsRemoved + $resultStats->constraintsRemoved,
            $this->containsUpdates || $resultStats->containsUpdates,
            $this->containsSystemUpdates || $resultStats->containsSystemUpdates,
            $this->systemUpdates + $resultStats->systemUpdates
        );
    }

    /**
     * Aggregates all the provided counters.
     *
     * @param iterable<SummaryCounters> $stats
     */
    public static function aggregate(iterable $stats): SummaryCounters
    {
        $tbr = new SummaryCounters();
        foreach ($stats as $stat) {
            $tbr = $tbr->merge($stat);
        }

        return $tbr;
    }

    public function toArray(): array
    {
        return [
            'nodesCreated' => $this->nodesCreated,
            'nodesDeleted' => $this->nodesDeleted,
            'relationshipsCreated' => $this->relationshipsCreated,
            'relationshipsDeleted' => $this->relationshipsDeleted,
            'propertiesSet' => $this->propertiesSet,
            'labelsAdded' => $this->labelsAdded,
            'labelsRemoved' => $this->labelsRemoved,
            'indexesAdded' => $this->indexesAdded,
            'indexesRemoved' => $this->indexesRemoved,
            'constraintsAdded' => $this->constraintsAdded,
            'constraintsRemoved' => $this->constraintsRemoved,
            'containsUpdates' => $this->containsUpdates,
            'containsSystemUpdates' => $this->containsSystemUpdates,
            'systemUpdates' => $this->systemUpdates,
        ];
    }
}
