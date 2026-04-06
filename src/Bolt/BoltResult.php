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

use function array_splice;

use Bolt\error\BoltException;
use Bolt\error\ConnectException as BoltConnectException;

use function count;

use Generator;

use function in_array;

use Iterator;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use RuntimeException;
use Throwable;

/**
 * @psalm-import-type BoltCypherStats from SummarizedResultFormatter
 *
 * @implements Iterator<int, list<mixed>>
 */
final class BoltResult implements Iterator
{
    /** @var list<list> */
    private array $rows = [];
    private ?array $meta = null;
    /** @var list<(callable(array):void)> */
    private array $finishedCallbacks = [];

    public function __construct(
        private readonly BoltConnection $connection,
        private readonly int $fetchSize,
        private readonly int $qid,
    ) {
    }

    public function getFetchSize(): int
    {
        return $this->fetchSize;
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
     * @return Generator<int, list>
     */
    public function getIt(): Generator
    {
        if ($this->it === null) {
            $this->it = $this->iterator();
        }

        return $this->it;
    }

    /**
     * @return Generator<int, list<mixed>>
     */
    public function iterator(): Generator
    {
        $i = 0;
        while ($this->meta === null) {
            $this->fetchResults();
            foreach ($this->rows as $row) {
                yield $i => $row;
                ++$i;
            }
            // If meta is set to empty array (normal completion with no records), exit immediately
            if ($this->meta === []) {
                break;
            }
        }

        $meta = $this->meta;
        // Finished callbacks are callable(array): void and read summary keys (e.g. db, bookmark); passing null
        // would error at runtime. We only run them when we have a real completion summary—if meta is still null
        // (e.g. partial pull / more fetches pending), there is nothing to hand to the callback yet.
        if ($meta !== null) {
            foreach ($this->finishedCallbacks as $finishedCallback) {
                $finishedCallback($meta);
            }
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
        try {
            $meta = $this->connection->pull($this->qid, $this->fetchSize);
        } catch (BoltConnectException|BoltException $e) {
            // Invalidate connection on socket/network errors so pool does not reuse it.
            // Rethrow as-is - Session retry logic inspects the actual exception via isConnectionError().
            $this->connection->invalidate();
            throw $e;
        }
        // Neo4jException and other Throwable propagate naturally - no invalidate needed for server errors

        // Safety check: ensure pull response $meta is not empty (pull() is typed non-empty-list but we defend against empty)
        /** @psalm-suppress TypeDoesNotContainType */
        if (empty($meta)) {
            throw new RuntimeException('Empty response from server');
        }

        /** @var list<list> $rows */
        $rows = array_splice($meta, 0, count($meta) - 1);
        $this->rows = $rows;

        /** @var array{0: array} $meta */
        // Check if we have a valid summary
        /** @psalm-suppress RedundantConditionGivenDocblockType */
        if (count($meta) > 0 && is_array($meta[0])) {
            if (isset($meta[0][BoltConnection::LAUDIS_BOLT_PARTIAL_PULL_MARKER])) {
                // Synthetic summary from BoltConnection::pull() after disconnect mid-response
                $this->meta = null;
            } elseif (empty($meta[0]) && empty($rows)) {
                // Normal completion with no records - mark as complete
                $this->meta = [];
            } elseif (!empty($meta[0])) {
                // Valid summary with data
                if (!array_key_exists('has_more', $meta[0]) || $meta[0]['has_more'] === false) {
                    $this->meta = $meta[0];
                }
            } elseif (!empty($rows)) {
                // Real Bolt SUCCESS {} after records (empty map decodes to []): stream finished
                $this->meta = [];
            } else {
                $this->meta = null;
            }
        } else {
            // No summary received (connection closed before summary)
            // Set $this->meta to null so the next fetchResults() will try to pull again
            $this->meta = null;
        }
    }

    /**
     * @psalm-suppress InvalidNullableReturnType
     *
     * @return list<mixed>
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
