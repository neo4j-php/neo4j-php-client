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

namespace Laudis\Neo4j\Bolt;

use Laudis\Neo4j\Exception\Neo4jException;

/**
 * Internal result of a Bolt PULL. May carry a deferred server failure when
 * RECORD rows were received before a Bolt FAILURE in the same pull response.
 *
 * @internal
 */
final class PullResult
{
    /**
     * @param list<array<array-key, mixed>> $recordRows
     * @param array<string, mixed>|null     $summary
     */
    private function __construct(
        private readonly array $recordRows,
        private readonly ?array $summary,
        private readonly ?Neo4jException $deferredFailure,
    ) {
    }

    /**
     * @param non-empty-list<array<array-key, mixed>> $content record rows followed by a summary map
     */
    public static function complete(array $content): self
    {
        /** @var array<string, mixed> $summary */
        $summary = array_pop($content);

        return new self($content, $summary, null);
    }

    /**
     * @param non-empty-list<array<array-key, mixed>> $bufferedRows
     */
    public static function withDeferredFailure(array $bufferedRows, Neo4jException $failure): self
    {
        return new self($bufferedRows, null, $failure);
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function getRecordRows(): array
    {
        return $this->recordRows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSummary(): ?array
    {
        return $this->summary;
    }

    public function getDeferredFailure(): ?Neo4jException
    {
        return $this->deferredFailure;
    }
}
