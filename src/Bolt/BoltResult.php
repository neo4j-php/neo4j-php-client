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

use function array_key_exists;

use Bolt\error\BoltException;
use Bolt\error\ConnectException as BoltConnectException;
use Generator;

use function in_array;
use function is_array;

use Iterator;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Throwable;

/**
 * @psalm-import-type BoltCypherStats from SummarizedResultFormatter
 *
 * @implements Iterator<int, array<array-key, mixed>>
 */
final class BoltResult implements Iterator
{
    /** @var list<array<array-key, mixed>> */
    private array $rows = [];
    private ?array $meta = null;
    private ?Neo4jException $deferredFailure = null;
    /** @var list<(callable(array):void)> */
    private array $finishedCallbacks = [];

    public function __construct(
        private readonly BoltConnection $connection,
        private readonly int $fetchSize,
        private readonly int $qid,
    ) {
    }

    /**
     * Remaining server pulls use PULL n=-1 (TestKit Optimization:ResultListFetchAll / list()).
     */
    private ?int $pullOverrideSize = null;

    /**
     * True after at least one {@see fetchResults()} (network pull). Used so list() can reset a stale
     * cached generator before the first pull, but must not reset after next()+list() or rows replay.
     */
    private bool $networkPullOccurred = false;

    public function prepareForResultListFetchAll(): void
    {
        $this->pullOverrideSize = -1;
        // Drop cached generator only if no pull ran yet (e.g. valid()/getIt() touched before list()).
        // If next() already ran, resetting would restart iterator() and duplicate records on list().
        if ($this->it !== null && !$this->networkPullOccurred) {
            $this->it = null;
        }
    }

    public function getFetchSize(): int
    {
        return $this->fetchSize;
    }

    private function effectivePullSize(): int
    {
        return $this->pullOverrideSize ?? $this->fetchSize;
    }

    private ?Generator $it = null;

    /**
     * @param callable(array):void $finishedCallback
     */
    public function addFinishedCallback(callable $finishedCallback): void
    {
        $this->finishedCallbacks[] = $finishedCallback;
    }

    /**
     * @return Generator<int, array<array-key, mixed>>
     */
    public function getIt(): Generator
    {
        if ($this->it === null) {
            $this->it = $this->iterator();
        }

        return $this->it;
    }

    /**
     * @return Generator<int, array<array-key, mixed>>
     */
    public function iterator(): Generator
    {
        $i = 0;
        do {
            if ($this->rows === [] && $this->meta === null) {
                $this->fetchResults();
            }

            foreach ($this->takeRows() as $row) {
                yield $i => $row;
                ++$i;
            }
        } while ($this->meta === null || $this->rows !== []);
    }

    /**
     * Takes the pending rows, so buffer() can append new ones while these are being yielded.
     *
     * @return list<array<array-key, mixed>>
     */
    private function takeRows(): array
    {
        $rows = $this->rows;
        $this->rows = [];

        return $rows;
    }

    /**
     * Pulls all remaining records into memory, so the connection can be reused before they are read.
     *
     * A deferred failure stops the pull without throwing; it is raised once the buffered records are read.
     */
    public function buffer(): void
    {
        while ($this->meta === null && $this->deferredFailure === null) {
            $this->fetchResults();
        }
    }

    public function consume(): array
    {
        while ($this->valid()) {
            $this->next();
        }

        return $this->meta ?? [];
    }

    private function fetchResults(): void
    {
        if ($this->deferredFailure !== null) {
            throw $this->deferredFailure;
        }

        $this->networkPullOccurred = true;

        try {
            $pullResult = $this->connection->pull($this->qid, $this->effectivePullSize());
        } catch (BoltConnectException|BoltException $e) {
            // Invalidate connection on socket/network errors so pool does not reuse it.
            // Rethrow as-is - Session retry logic inspects the actual exception via isConnectionError().
            $this->connection->invalidate();
            throw $e;
        }
        // Neo4jException and other Throwable propagate naturally - no invalidate needed for server errors

        $batch = $pullResult->getRecordRows();
        array_push($this->rows, ...$batch);

        $deferredFailure = $pullResult->getDeferredFailure();
        if ($deferredFailure !== null) {
            $this->deferredFailure = $deferredFailure;

            return;
        }

        $summarySlot = $pullResult->getSummary();
        if (!is_array($summarySlot)) {
            // No summary received (connection closed before summary)
            $this->meta = null;

            return;
        }

        $summaryEmpty = $summarySlot === [];
        $hasDataRows = $batch !== [];

        if ($summaryEmpty && !$hasDataRows) {
            // Normal completion with no records
            $this->meta = [];
        } elseif (!$summaryEmpty) {
            // Valid summary map (e.g. has_more, counters, db, …)
            if (!array_key_exists('has_more', $summarySlot) || $summarySlot['has_more'] === false) {
                $this->meta = $summarySlot;
            }
        } else {
            // Empty summary slot with data rows: Bolt SUCCESS {} after RECORDs — stream complete (no has_more keys).
            $this->meta = $summarySlot;
        }

        if ($this->meta !== null) {
            foreach ($this->finishedCallbacks as $finishedCallback) {
                $finishedCallback($this->meta);
            }
        }
    }

    /**
     * @psalm-suppress InvalidNullableReturnType
     *
     * @return array<array-key, mixed>
     */
    public function current(): array
    {
        /**
         * @psalm-suppress NullableReturnStatement
         */
        return $this->getIt()->current();
    }

    public function next(): void
    {
        $this->getIt()->next();
    }

    public function key(): int
    {
        return $this->getIt()->key();
    }

    public function valid(): bool
    {
        return $this->getIt()->valid();
    }

    public function rewind(): void
    {
        // Rewind is impossible
    }

    public function __destruct()
    {
        if ($this->meta === null && in_array($this->connection->getServerState(), ['STREAMING', 'TX_STREAMING'], true)) {
            $this->discard();
        }
    }

    public function discard(): void
    {
        try {
            $this->connection->discard($this->qid === -1 ? null : $this->qid);
        } catch (BoltConnectException|BoltException $e) {
            // Connection already broken if DISCARD fails. Invalidate to prevent pool from reusing it.
            // Don't rethrow: this is called from __destruct() where exceptions don't propagate properly.
            $this->connection->invalidate();
        }
    }
}
