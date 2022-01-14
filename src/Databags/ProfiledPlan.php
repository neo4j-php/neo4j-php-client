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
use Laudis\Neo4j\Types\CypherList;

/**
 * A plan that has been executed. This means a lot more information is available.
 *
 * @see \Laudis\Neo4j\Databags\Plan
 *
 * @psalm-immutable
 */
final class ProfiledPlan extends AbstractCypherObject
{
    /** @var CypherList<ProfiledPlan> */
    private CypherList $children;
    private int $dbHits;
    private bool $hasPageCacheStats;
    private float $pageCacheHitRatio;
    private int $pageCacheHits;
    private int $pageCacheMisses;
    private int $records;
    private int $time;

    /**
     * @param CypherList<ProfiledPlan> $children
     */
    public function __construct(
        CypherList $children,
        int $dbHits,
        bool $hasPageCacheStats,
        float $pageCacheHitRatio,
        int $pageCacheHits,
        int $pageCacheMisses,
        int $records,
        int $time
    ) {
        $this->children = $children;
        $this->dbHits = $dbHits;
        $this->hasPageCacheStats = $hasPageCacheStats;
        $this->pageCacheHitRatio = $pageCacheHitRatio;
        $this->pageCacheHits = $pageCacheHits;
        $this->pageCacheMisses = $pageCacheMisses;
        $this->records = $records;
        $this->time = $time;
    }

    /**
     * @return CypherList<ProfiledPlan>
     */
    public function getChildren(): CypherList
    {
        return $this->children;
    }

    /**
     * The number of times this part of the plan touched the underlying data stores.
     */
    public function getDbHits(): int
    {
        return $this->dbHits;
    }

    /**
     * If the number page cache hits and misses and the ratio was recorded.
     */
    public function hasPageCacheStats(): bool
    {
        return $this->hasPageCacheStats;
    }

    /**
     * The ratio of page cache hits to total number of lookups or 0 if no data is available.
     */
    public function getPageCacheHitRatio(): float
    {
        return $this->pageCacheHitRatio;
    }

    /**
     * Number of page cache hits caused by executing the associated execution step.
     */
    public function getPageCacheHits(): int
    {
        return $this->pageCacheHits;
    }

    /**
     * Number of page cache misses caused by executing the associated execution step.
     */
    public function getPageCacheMisses(): int
    {
        return $this->pageCacheMisses;
    }

    /**
     * The number of records this part of the plan produced.
     */
    public function getRecords(): int
    {
        return $this->records;
    }

    /**
     * Amount of time spent in the associated execution step.
     */
    public function getTime(): int
    {
        return $this->time;
    }

    public function toArray(): array
    {
        return [
            'children' => $this->children,
            'dbHits' => $this->dbHits,
            'hasPageCacheStats' => $this->hasPageCacheStats,
            'pageCacheHitRatio' => $this->pageCacheHitRatio,
            'pageCacheHits' => $this->pageCacheHits,
            'pageCacheMisses' => $this->pageCacheMisses,
            'records' => $this->records,
            'time' => $this->time,
        ];
    }
}
