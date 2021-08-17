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

use Laudis\Neo4j\Types\CypherList;

final class ProfiledPlan
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

    public function getDbHits(): int
    {
        return $this->dbHits;
    }

    public function isHasPageCacheStats(): bool
    {
        return $this->hasPageCacheStats;
    }

    public function getPageCacheHitRatio(): float
    {
        return $this->pageCacheHitRatio;
    }

    public function getPageCacheHits(): int
    {
        return $this->pageCacheHits;
    }

    public function getPageCacheMisses(): int
    {
        return $this->pageCacheMisses;
    }

    public function getRecords(): int
    {
        return $this->records;
    }

    public function getTime(): int
    {
        return $this->time;
    }
}
