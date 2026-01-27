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
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;

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
        }

        foreach ($this->finishedCallbacks as $finishedCallback) {
            $finishedCallback($this->meta);
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
        // Catch socket/connection errors during PULL. Convert BoltConnectException to Neo4jException
        // so Session retry logic can detect and handle connection failures (triggers routing table refresh).
        try {
            $meta = $this->connection->pull($this->qid, $this->fetchSize);
        } catch (BoltConnectException $e) {
            // Close connection on socket errors
            $this->connection->invalidate();
            // Convert to Neo4jException with NotALeader code so Session.executeStatementWithRetry()
            // and Session.retry() can catch it and clear routing table for automatic failover recovery.
            throw new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Cluster.NotALeader', 'Connection error: '.$e->getMessage())], $e);
        } catch (BoltException $e) {
            // Close connection on Bolt protocol errors (includes disconnect errors)
            $this->connection->invalidate();
            throw new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Cluster.NotALeader', 'Connection error: '.$e->getMessage())], $e);
        } catch (Neo4jException $e) {
            // Re-throw Neo4jExceptions that were already processed by BoltMessage
            throw $e;
        } catch (\Throwable $e) {
            // Close connection on any other errors
            $this->connection->invalidate();
            throw new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Cluster.NotALeader', 'Connection error: '.$e->getMessage())], $e);
        }

        // Safety check: ensure $meta is not empty
        if (empty($meta)) {
            throw new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Cluster.NotALeader', 'Empty response from server')]);
        }

        /** @var list<list> $rows */
        $rows = array_splice($meta, 0, count($meta) - 1);
        $this->rows = $rows;

        /** @var array{0: array} $meta */
        // Check if we have a valid summary (not empty array from partial pull)
        if (count($meta) > 0 && !empty($meta[0]) && is_array($meta[0])) {
            if (!array_key_exists('has_more', $meta[0]) || $meta[0]['has_more'] === false) {
                $this->meta = $meta[0];
            }
        } else {
            // Partial result - no summary received (connection closed after records)
            // Don't set $this->meta so the next fetchResults() will try to pull again
            // This allows the first record to be consumed, and the next fetch will fail
            // which is the expected behavior for tests like exit_after_record
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
        } catch (BoltConnectException $e) {
            // Connection already broken if DISCARD fails. Invalidate to prevent pool from reusing it.
            // Don't rethrow: this is called from __destruct() where exceptions don't propagate properly.
            // Connection will be detected as broken on next operation when pool tries to reuse it.
            $this->connection->invalidate();
            // Ignore connection errors during discard - connection is already broken
            // The Neo4jException will be thrown when the next operation is attempted
        } catch (BoltException $e) {
            $this->connection->invalidate();
            // Ignore Bolt protocol errors during discard - connection is already broken
        }
    }
}
