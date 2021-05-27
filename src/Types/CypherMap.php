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
use Ds\Map;
use Ds\Pair;
use Ds\Sequence;
use Ds\Set;
use IteratorAggregate;

final class CypherMap implements ArrayAccess, IteratorAggregate
{
    private Map $map;

    public function __construct(Map $map)
    {
        $this->map = $map;
    }

    public function count(): int
    {
        return $this->map->count();
    }

    public function copy()
    {
        return $this->map->copy();
    }

    public function isEmpty(): bool
    {
        return $this->map->isEmpty();
    }

    public function toArray(): array
    {
        return $this->map->toArray();
    }

    public function getIterator()
    {
        return $this->map->getIterator();
    }

    public function offsetExists($offset)
    {
        return $this->map->offsetExists($offset);
    }

    public function offsetGet($offset)
    {
        return $this->map->offsetGet($offset);
    }

    public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException('A cypher map is immutable');
    }

    public function offsetUnset($offset)
    {
        throw new BadMethodCallException('A cypher map is immutable');
    }

    public function jsonSerialize()
    {
        return $this->map->jsonSerialize();
    }

    public function first(): Pair
    {
        return $this->map->first();
    }

    public function last(): Pair
    {
        return $this->map->last();
    }

    public function skip(int $position): Pair
    {
        return $this->map->skip($position);
    }

    public function merge($values): Map
    {
        return $this->map->merge($values);
    }

    public function intersect(Map $map): Map
    {
        return $this->map->intersect($map);
    }

    public function diff(Map $map): Map
    {
        return $this->map->diff($map);
    }

    public function hasKey($key): bool
    {
        return $this->map->hasKey($key);
    }

    /**
     * Returns whether an association for a given value exists.
     *
     * @param mixed $value
     */
    public function hasValue($value): bool
    {
        return $this->map->hasValue($value);
    }

    public function filter(callable $callback = null): Map
    {
        return $this->map->filter($callback);
    }

    public function get($key, $default = null)
    {
        return $this->map->get($key, $default);
    }

    public function keys(): Set
    {
        return $this->map->keys();
    }

    public function map(callable $callback): Map
    {
        return $this->map->map($callback);
    }

    public function pairs(): Sequence
    {
        return $this->map->pairs();
    }

    public function reduce(callable $callback, $initial = null)
    {
        return $this->map->reduce($callback, $initial);
    }

    public function reversed(): Map
    {
        return $this->map->reversed();
    }

    public function slice(int $offset, int $length = null): Map
    {
        return $this->map->slice($offset, $length);
    }

    public function sorted(callable $comparator = null): Map
    {
        return $this->map->sorted($comparator);
    }

    public function ksorted(callable $comparator = null): Map
    {
        return $this->map->ksorted($comparator);
    }

    public function sum()
    {
        return $this->map->sum();
    }

    public function values(): Sequence
    {
        return $this->map->values();
    }

    public function union(Map $map): Map
    {
        return $this->map->union($map);
    }

    public function xor(Map $map): Map
    {
        return $this->map->xor($map);
    }

    public function __debugInfo()
    {
        return $this->pairs()->toArray();
    }
}
