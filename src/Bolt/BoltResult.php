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
use BadMethodCallException;
use Bolt\protocol\V3;
use Bolt\protocol\V4;
use function call_user_func;
use function count;
use Exception;
use Iterator;

/**
 * @psalm-import-type BoltCypherStats from \Laudis\Neo4j\Contracts\FormatterInterface
 *
 * @implements Iterator<int, list>
 */
final class BoltResult implements Iterator
{
    private V3 $protocol;
    private int $fetchSize;
    /** @var list<list> */
    private array $rows = [];
    private int $current = 0;
    private ?array $meta = null;
    /** @var callable(array):void|null */
    private $finishedCallback;

    public function __construct(V3 $protocol, int $fetchSize)
    {
        $this->protocol = $protocol;
        $this->fetchSize = $fetchSize;
    }

    private function isDone(): bool
    {
        return $this->meta !== null && $this->cacheKey() >= count($this->rows);
    }

    /**
     * @throws Exception
     */
    private function prefetchNeeded(): void
    {
        if (!$this->isDone() && $this->cacheKey() === 0) {
            $this->fetchResults();
        }
    }

    /**
     * @return list
     */
    public function current(): array
    {
        return $this->rows[$this->cacheKey()];
    }

    /**
     * @param callable(array):void $finishedCallback
     */
    public function setFinishedCallback(callable $finishedCallback): void
    {
        $this->finishedCallback = $finishedCallback;
    }

    public function next(): void
    {
        ++$this->current;
        $this->prefetchNeeded();
        if ($this->isDone() &&
            $this->finishedCallback &&
            $this->current % $this->fetchSize === 0
        ) {
            call_user_func($this->finishedCallback, $this->meta ?? []);
        }
    }

    public function key()
    {
        return $this->current;
    }

    public function valid(): bool
    {
        return array_key_exists($this->cacheKey(), $this->rows);
    }

    public function rewind(): void
    {
        throw new BadMethodCallException('Cannot rewind a bolt result');
    }

    private function cacheKey(): int
    {
        return $this->current % $this->fetchSize;
    }

    /**
     * @return BoltCypherStats
     */
    public function consume(): array
    {
        while ($this->isDone()) {
            $this->fetchResults();
        }

        /** @var BoltCypherStats */
        return $this->meta;
    }

    /**
     * @return non-empty-list<list>
     */
    private function pull(): array
    {
        if (!$this->protocol instanceof V4) {
            /** @var non-empty-list<list> */
            return $this->protocol->pullAll();
        }

        /** @var non-empty-list<list> */
        return $this->protocol->pull(['n' => $this->fetchSize]);
    }

    private function fetchResults(): void
    {
        $meta = $this->pull();

        /** @var list<list> $rows */
        $rows = array_splice($meta, 0, count($meta) - 1);
        $this->rows = $rows;

        /** @var array{0: array} $meta */
        if ($meta[0]['has_more'] ?? false) {
            $this->meta = $meta[0];
        }
    }
}
