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
    /** @var list<list<mixed>> */
    private readonly array $recordRows;

    /**
     * @param list<list<mixed>>              $recordRows
     * @param array<string, mixed>|null     $summary
     */
    private function __construct(
        array $recordRows,
        private readonly ?array $summary,
        private readonly ?Neo4jException $deferredFailure,
    ) {
        $this->recordRows = $recordRows;
    }

    /**
     * @param list<array<array-key, mixed>> $content
     */
    public static function complete(array $content): self
    {
        if ($content === []) {
            throw new \LogicException('PULL response must not be empty');
        }

        /** @var array<string, mixed> $summary */
        $summary = array_pop($content);

        return new self(
            self::normalizeRows($content),
            $summary,
            null,
        );
    }

    /**
     * @param non-empty-list<array<array-key, mixed>> $bufferedRows
     */
    public static function withDeferredFailure(array $bufferedRows, Neo4jException $failure): self
    {
        return new self(self::normalizeRows($bufferedRows), null, $failure);
    }

    /**
     * @return list<list<mixed>>
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

    /**
     * @param list<array<array-key, mixed>> $rows
     *
     * @return list<list<mixed>>
     */
    private static function normalizeRows(array $rows): array
    {
        $normalized = [];
        foreach ($rows as $row) {
            $normalized[] = array_is_list($row) ? $row : array_values($row);
        }

        return $normalized;
    }
}
