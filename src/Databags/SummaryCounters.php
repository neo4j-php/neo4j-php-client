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

use Laudis\Neo4j\Types\AbstractCypherObject;

/**
 * Contains counters for various operations that a query triggered.
 *
 * @psalm-immutable
 */
final class SummaryCounters extends AbstractCypherObject
{
    private int $nodesCreated;

    private int $nodesDeleted;

    private int $relationshipsCreated;

    private int $relationshipsDeleted;

    private int $propertiesSet;

    private int $labelsAdded;

    private int $labelsRemoved;

    private int $indexesAdded;

    private int $indexesRemoved;

    private int $constraintsAdded;

    private int $constraintsRemoved;

    private bool $containsUpdates;

    private bool $containsSystemUpdates;

    private int $systemUpdates;

    public function __construct(
        int $nodesCreated = 0,
        int $nodesDeleted = 0,
        int $relationshipsCreated = 0,
        int $relationshipsDeleted = 0,
        int $propertiesSet = 0,
        int $labelsAdded = 0,
        int $labelsRemoved = 0,
        int $indexesAdded = 0,
        int $indexesRemoved = 0,
        int $constraintsAdded = 0,
        int $constraintsRemoved = 0,
        bool $containsUpdates = false,
        bool $containsSystemUpdates = false,
        int $systemUpdates = 0
    ) {
        $this->nodesCreated = $nodesCreated;
        $this->nodesDeleted = $nodesDeleted;
        $this->relationshipsCreated = $relationshipsCreated;
        $this->relationshipsDeleted = $relationshipsDeleted;
        $this->propertiesSet = $propertiesSet;
        $this->labelsAdded = $labelsAdded;
        $this->labelsRemoved = $labelsRemoved;
        $this->indexesAdded = $indexesAdded;
        $this->indexesRemoved = $indexesRemoved;
        $this->constraintsAdded = $constraintsAdded;
        $this->constraintsRemoved = $constraintsRemoved;
        $this->containsUpdates = $containsUpdates;
        $this->containsSystemUpdates = $containsSystemUpdates;
        $this->systemUpdates = $systemUpdates;
    }

    /**
     * Whether or not the query contained any updates.
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
