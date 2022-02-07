<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Bolt;

use function array_merge;
use function array_splice;
use ArrayAccess;
use BadMethodCallException;
use Bolt\protocol\V4;
use function count;
use IteratorAggregate;
use Traversable;

final class BoltResult implements IteratorAggregate, ArrayAccess
{
    private V4 $protocol;
    private int $fetchSize;
    private array $rows = [];
    private bool $done = false;

    public function __construct(V4 $protocol, int $fetchSize)
    {
        $this->protocol = $protocol;
        $this->fetchSize = $fetchSize;
    }

    public function getIterator(): Traversable
    {
        $i = 0;
        while ($this->offsetExists($i)) {
            yield $i => $this[$i];
            ++$i;
        }
    }

    public function offsetExists($offset): bool
    {
        $this->prefetchNeeded($offset);

        return $offset < count($this->rows);
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        $this->prefetchNeeded($offset);

        return $this->rows[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        throw new BadMethodCallException('Bolt results are immutable.');
    }

    public function offsetUnset($offset): void
    {
        throw new BadMethodCallException('Bolt results are immutable.');
    }

    /**
     * @throws \Exception
     */
    private function prefetchNeeded(int $offset): void
    {
        while (!$this->done && $offset >= count($this->rows)) {
            $meta = $this->protocol->pull(['n' => $this->fetchSize]);
            $rows = array_splice($meta, 0, count($meta) - 1);
            $this->rows = array_merge($this->rows, $rows);

            $this->done = !($meta[0]['has_more'] ?? false);
        }
    }
}
