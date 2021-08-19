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

use Laudis\Neo4j\Types\AbstractCypherContainer;

/**
 * @psalm-immutable
 */
final class SummaryCounters extends AbstractCypherContainer
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

    public function containsUpdates(): bool
    {
        return $this->containsUpdates;
    }

    public function nodesCreated(): int
    {
        return $this->nodesCreated;
    }

    public function nodesDeleted(): int
    {
        return $this->nodesDeleted;
    }

    public function relationshipsCreated(): int
    {
        return $this->relationshipsCreated;
    }

    public function relationshipsDeleted(): int
    {
        return $this->relationshipsDeleted;
    }

    public function propertiesSet(): int
    {
        return $this->propertiesSet;
    }

    public function labelsAdded(): int
    {
        return $this->labelsAdded;
    }

    public function labelsRemoved(): int
    {
        return $this->labelsRemoved;
    }

    public function indexesAdded(): int
    {
        return $this->indexesAdded;
    }

    public function indexesRemoved(): int
    {
        return $this->labelsRemoved;
    }

    public function constraintsAdded(): int
    {
        return $this->constraintsAdded;
    }

    public function constraintsRemoved(): int
    {
        return $this->constraintsRemoved;
    }

    public function containsSystemUpdates(): bool
    {
        return $this->containsSystemUpdates;
    }

    public function systemUpdates(): int
    {
        return $this->systemUpdates;
    }

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

    public function getIterator()
    {
        yield 'nodesCreated' => $this->nodesCreated;
        yield 'nodesDeleted' => $this->nodesDeleted;
        yield 'relationshipsCreated' => $this->relationshipsCreated;
        yield 'relationshipsDeleted' => $this->relationshipsDeleted;
        yield 'propertiesSet' => $this->propertiesSet;
        yield 'labelsAdded' => $this->labelsAdded;
        yield 'labelsRemoved' => $this->labelsRemoved;
        yield 'indexesAdded' => $this->indexesAdded;
        yield 'indexesRemoved' => $this->indexesRemoved;
        yield 'constraintsAdded' => $this->constraintsAdded;
        yield 'constraintsRemoved' => $this->constraintsRemoved;
        yield 'containsUpdates' => $this->containsUpdates;
        yield 'containsSystemUpdates' => $this->containsSystemUpdates;
        yield 'systemUpdates' => $this->systemUpdates;
    }
}
