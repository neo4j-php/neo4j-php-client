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

use BadMethodCallException;
use Ds\Map;
use Ds\Pair;
use Ds\Sequence;
use Ds\Set;
use Laudis\Neo4j\Contracts\CypherContainerInterface;
use OutOfBoundsException;
use Traversable;

/**
 * @template T
 *
 * @implements CypherContainerInterface<string, T>
 */
final class CypherMap implements CypherContainerInterface
{
    /** @var Map<string, T> */
    private Map $map;

    /**
     * @param Map<string, T> $map
     */
    public function __construct(Map $map)
    {
        $this->map = $map;
    }

    public function count(): int
    {
        return $this->map->count();
    }

    /**
     * @return CypherMap<T>
     */
    public function copy(): CypherMap
    {
        return new CypherMap($this->map->copy());
    }

    public function isEmpty(): bool
    {
        return $this->map->isEmpty();
    }

    /**
     * @return array<string, T>
     */
    public function toArray(): array
    {
        return $this->map->toArray();
    }

    public function getIterator()
    {
        /** @var Traversable<string, T> */
        return $this->map->getIterator();
    }

    /**
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return $this->map->offsetExists($offset);
    }

    /**
     * @param string $offset
     *
     * @return T
     */
    public function offsetGet($offset)
    {
        return $this->map->offsetGet($offset);
    }

    /**
     * @param string $offset
     * @param T      $value
     */
    public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException('A cypher map is immutable');
    }

    /**
     * @param string $offset
     */
    public function offsetUnset($offset)
    {
        throw new BadMethodCallException('A cypher map is immutable');
    }

    public function jsonSerialize()
    {
        return $this->map->jsonSerialize();
    }

    /**
     * @return Pair<string, T>
     */
    public function first(): Pair
    {
        return $this->map->first();
    }

    /**
     * @return Pair<string, T>
     */
    public function last(): Pair
    {
        return $this->map->last();
    }

    /**
     * @return Pair<string, T>
     */
    public function skip(int $position): Pair
    {
        return $this->map->skip($position);
    }

    /**
     * @param iterable<string, T> $values
     *
     * @return CypherMap<T>
     */
    public function merge($values): CypherMap
    {
        return new CypherMap($this->map->merge($values));
    }

    /**
     * @param Map<string, T>|CypherMap<T> $map
     *
     * @return CypherMap<T>
     */
    public function intersect($map): CypherMap
    {
        if ($map instanceof self) {
            $map = $map->map;
        }

        return new CypherMap($this->map->intersect($map));
    }

    /**
     * @param Map<string, T>|CypherMap<T> $map
     *
     * @return CypherMap<T>
     */
    public function diff($map): CypherMap
    {
        if ($map instanceof self) {
            $map = $map->map;
        }

        return new CypherMap($this->map->diff($map));
    }

    public function hasKey(string $key): bool
    {
        return $this->map->hasKey($key);
    }

    /**
     * @param T $value
     */
    public function hasValue($value): bool
    {
        return $this->map->hasValue($value);
    }

    /**
     * @param (callable(string, T):bool)|null $callback
     *
     * @return CypherMap<T>
     */
    public function filter(callable $callback = null): CypherMap
    {
        return new CypherMap($this->map->filter($callback));
    }

    /**
     * @template TDefault
     *
     * @param TDefault $default
     *
     * @throws OutOfBoundsException
     *
     * @return (
     *           func_num_args() is 1
     *           ? T
     *           : T|TDefault
     *           )
     *
     * @psalm-mutation-free
     */
    public function get(string $key, $default = null)
    {
        return $this->map->get($key, $default);
    }

    /**
     * @return Set<string>
     */
    public function keys(): Set
    {
        return $this->map->keys();
    }

    /**
     * @template U
     *
     * @param callable(string, T):U $callback
     *
     * @return CypherMap<U>
     */
    public function map(callable $callback): CypherMap
    {
        return new CypherMap($this->map->map($callback));
    }

    /**
     * @return Sequence<Pair<string, T>>
     */
    public function pairs(): Sequence
    {
        return $this->map->pairs();
    }

    /**
     * @param callable(T, string, T):T $callback
     * @param T|null                   $initial
     *
     * @return T
     */
    public function reduce(callable $callback, $initial = null)
    {
        return $this->map->reduce($callback, $initial);
    }

    /**
     * @return CypherMap<T>
     */
    public function reversed(): CypherMap
    {
        return new CypherMap($this->map->reversed());
    }

    /**
     * @return CypherMap<T>
     */
    public function slice(int $offset, int $length = null): CypherMap
    {
        return new CypherMap($this->map->slice($offset, $length));
    }

    /**
     * @param callable(T):int $comparator
     *
     * @return CypherMap<T>
     */
    public function sorted(callable $comparator = null): CypherMap
    {
        return new CypherMap($this->map->sorted($comparator));
    }

    /**
     * @param callable(string):int $comparator
     *
     * @return CypherMap<T>
     */
    public function ksorted(callable $comparator = null): CypherMap
    {
        return new CypherMap($this->map->ksorted($comparator));
    }

    /**
     * @return float|int
     */
    public function sum()
    {
        return $this->map->sum();
    }

    /**
     * @return Sequence<T>
     */
    public function values(): Sequence
    {
        return $this->map->values();
    }

    /**
     * @param CypherMap<T>|Map<string, T> $map
     *
     * @return CypherMap<T>
     */
    public function union($map): CypherMap
    {
        if ($map instanceof self) {
            $map = $map->map;
        }

        return new CypherMap($this->map->union($map));
    }

    /**
     * @param CypherMap<T>|Map<string, T> $map
     *
     * @return CypherMap<T>
     */
    public function xor($map): CypherMap
    {
        if ($map instanceof self) {
            $map = $map->map;
        }

        return new CypherMap($this->map->xor($map));
    }

    public function __debugInfo()
    {
        return $this->pairs()->toArray();
    }
}
