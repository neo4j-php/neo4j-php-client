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

final class PlanArguments
{
    public function __construct(
        public readonly ?int $globalMemory = null,
        public readonly ?string $plannerImpl = null,
        public readonly ?int $memory = null,
        public readonly ?string $stringRepresentation = null,
        public readonly ?string $runtime = null,
        public readonly ?int $time = null,
        public readonly ?int $pageCacheMisses = null,
        public readonly ?int $pageCacheHits = null,
        public readonly ?string $runtimeImpl = null,
        public readonly ?string $version = null,
        public readonly ?int $dbHits = null,
        public readonly ?int $batchSize = null,
        public readonly ?string $details = null,
        public readonly ?string $plannerVersion = null,
        public readonly ?string $pipelineInfo = null,
        public readonly string|float|null $runtimeVersion = null,
        public readonly ?int $id = null,
        public readonly ?float $estimatedRows = null,
        public readonly ?string $planner = null,
        public readonly ?int $rows = null,
    ) {
    }

    /**
     * @psalm-external-mutation-free
     */
    public function toArray(): array
    {
        return [
            'globalMemory' => $this->globalMemory,
            'plannerImpl' => $this->plannerImpl,
            'memory' => $this->memory,
            'stringRepresentation' => $this->stringRepresentation,
            'runtime' => $this->runtime,
            'time' => $this->time,
            'pageCacheMisses' => $this->pageCacheMisses,
            'pageCacheHits' => $this->pageCacheHits,
            'runtimeImpl' => $this->runtimeImpl,
            'version' => $this->version,
            'dbHits' => $this->dbHits,
            'batchSize' => $this->batchSize,
            'details' => $this->details,
            'plannerVersion' => $this->plannerVersion,
            'pipelineInfo' => $this->pipelineInfo,
            'runtimeVersion' => $this->runtimeVersion,
            'id' => $this->id,
            'estimatedRows' => $this->estimatedRows,
            'planner' => $this->planner,
            'rows' => $this->rows,
        ];
    }
}
