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

namespace Laudis\Neo4j\Types;

use ArrayAccess;
use BadMethodCallException;
use Ds\Sequence;
use Ds\Vector;
use IteratorAggregate;

final class CypherList implements IteratorAggregate, ArrayAccess
{
    private Vector $vector;

    public function __construct(Vector $vector)
    {
        $this->vector = $vector;
    }

    public function count(): int
    {
        return $this->vector->count();
    }

    public function copy()
    {
        return $this->vector->copy();
    }

    public function isEmpty(): bool
    {
        return $this->vector->isEmpty();
    }

    public function toArray(): array
    {
        return $this->vector->toArray();
    }

    public function getIterator()
    {
        return $this->vector->getIterator();
    }

    public function offsetExists($offset)
    {
        return $this->vector->offsetExists();
    }

    public function offsetGet($offset)
    {
        return $this->vector->offsetGet($offset);
    }

    public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException('A cypher list is immutable');
    }

    public function offsetUnset($offset)
    {
        throw new BadMethodCallException('A cypher list is immutable');
    }

    public function contains(...$values): bool
    {
        return $this->vector->contains(...$values);
    }

    public function filter(callable $callback = null): Sequence
    {
        return $this->vector->filter($callback);
    }

    public function find($value)
    {
        return $this->vector->find($value);
    }

    public function first()
    {
        return $this->vector->first();
    }

    public function get(int $index)
    {
        return $this->vector->get($index);
    }

    public function join(string $glue = null): string
    {
        return $this->vector->join($glue);
    }

    public function last()
    {
        return $this->vector->last();
    }

    public function map(callable $callback): Sequence
    {
        return $this->vector->map($callback);
    }

    public function merge($values): Sequence
    {
        return $this->vector->merge($values);
    }

    public function reduce(callable $callback, $initial = null)
    {
        return $this->vector->reduce($callback, $initial);
    }

    public function reversed()
    {
        return $this->vector->reversed();
    }

    public function slice(int $index, int $length = null): Sequence
    {
        return $this->vector->slice($index, $length);
    }

    public function sorted(callable $comparator = null): Sequence
    {
        return $this->vector->sorted($comparator);
    }

    public function sum()
    {
        return $this->vector->sum();
    }

    public function jsonSerialize()
    {
        return $this->vector->jsonSerialize();
    }

    public function __debugInfo()
    {
        return $this->vector->toArray();
    }
}
