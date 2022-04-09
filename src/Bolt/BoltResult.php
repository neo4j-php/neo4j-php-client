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

namespace Laudis\Neo4j\Bolt;

use function array_splice;
use function call_user_func;
use function count;
use Generator;
use Iterator;

/**
 * @psalm-import-type BoltCypherStats from \Laudis\Neo4j\Contracts\FormatterInterface
 *
 * @implements Iterator<int, list>
 */
final class BoltResult implements Iterator
{
    private BoltConnection $connection;
    private int $fetchSize;
    /** @var list<list> */
    private array $rows = [];
    private ?array $meta = null;
    /** @var (callable(array):void)|null */
    private $finishedCallback;
    private int $qid;

    public function __construct(BoltConnection $connection, int $fetchSize, int $qid)
    {
        $this->connection = $connection;
        $this->fetchSize = $fetchSize;
        $this->qid = $qid;
    }

    public function getFetchSize(): int
    {
        return $this->fetchSize;
    }

    private ?Generator $it = null;

    /**
     * @param callable(array):void $finishedCallback
     */
    public function setFinishedCallback(callable $finishedCallback): void
    {
        $this->finishedCallback = $finishedCallback;
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
     * @return Generator<int, list>
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

        if ($this->finishedCallback) {
            call_user_func($this->finishedCallback, $this->meta);
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
        $meta = $this->connection->pull($this->qid, $this->fetchSize);

        /** @var list<list> $rows */
        $rows = array_splice($meta, 0, count($meta) - 1);
        $this->rows = $rows;

        /** @var array{0: array} $meta */
        if (!array_key_exists('has_more', $meta[0]) || $meta[0]['has_more'] === false) {
            $this->meta = $meta[0];
        }
    }

    /**
     * @return list
     */
    public function current(): array
    {
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
        $this->connection->discard($this->qid === -1 ? null : $this->qid);
    }
}
