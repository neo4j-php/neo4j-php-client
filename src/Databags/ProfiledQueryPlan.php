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

final class ProfiledQueryPlan
{
    /**
     * @param list<ProfiledQueryPlan> $children
     * @param list<string>            $identifiers
     */
    public function __construct(
        public readonly PlanArguments $arguments,
        public readonly int $dbHits = 0,
        public readonly int $records = 0,
        public readonly bool $hasPageCacheStats = false,
        public readonly int $pageCacheHits = 0,
        public readonly int $pageCacheMisses = 0,
        public readonly float $pageCacheHitRatio = 0.0,
        public readonly int $time = 0,
        public readonly string $operatorType = '',
        public readonly array $children = [],
        public readonly array $identifiers = [],
    ) {
    }

    public function toArray(): array
    {
        return [
            'arguments' => $this->arguments->toArray(),
            'dbHits' => $this->dbHits,
            'records' => $this->records,
            'hasPageCacheStats' => $this->hasPageCacheStats,
            'pageCacheHits' => $this->pageCacheHits,
            'pageCacheMisses' => $this->pageCacheMisses,
            'pageCacheHitRatio' => $this->pageCacheHitRatio,
            'time' => $this->time,
            'operatorType' => $this->operatorType,
            'children' => array_map(
                static fn (ProfiledQueryPlan $child): array => $child->toArray(),
                $this->children
            ),
            'identifiers' => $this->identifiers,
        ];
    }
}
