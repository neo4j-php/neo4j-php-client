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

use function array_key_exists;
use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_reverse;
use function array_slice;
use function array_sum;
use function array_values;
use ArrayIterator;
use BadMethodCallException;
use function count;
use function func_num_args;
use function in_array;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use Laudis\Neo4j\Contracts\CypherContainerInterface;
use Laudis\Neo4j\Databags\Pair;
use OutOfBoundsException;
use function ksort;
use function sort;
use Traversable;
use function uksort;
use function usort;

/**
 * @template T
 *
 * @implements CypherContainerInterface<string, T>
 *
 * @psalm-immutable
 */
final class CypherMap implements CypherContainerInterface
{
    /** @var array<string, T> */
    private array $map;

    /**
     * @param iterable<string, T> $map
     */
    public function __construct(iterable $map = [])
    {
        if ($map instanceof self) {
            $this->map = $map->map;
        } elseif (is_array($map)) {
            $this->map = $map;
        } else {
            $this->map = [];
            foreach ($map as $key => $value) {
                $this->map[$key] = $value;
            }
        }
    }

    public function count(): int
    {
        return count($this->map);
    }

    /**
     * @return CypherMap<T>
     */
    public function copy(): CypherMap
    {
        $map = $this->map;

        return new CypherMap($map);
    }

    public function isEmpty(): bool
    {
        return count($this->map) === 0;
    }

    /**
     * @return array<string, T>
     */
    public function toArray(): array
    {
        return $this->map;
    }

    public function getIterator()
    {
        /** @var Traversable<string, T> */
        return new ArrayIterator($this->map);
    }

    /**
     * @param string $offset
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->map);
    }

    /**
     * @param string $offset
     *
     * @return T
     */
    public function offsetGet($offset)
    {
        return $this->map[$offset];
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

    public function jsonSerialize(): array
    {
        return $this->map;
    }

    /**
     * @return Pair<string, T>
     */
    public function first(): Pair
    {
        $key = array_key_first($this->map);
        if (!is_string($key)) {
            throw new BadMethodCallException('Cannot grab first element from an empty map');
        }

        return new Pair($key, $this->map[$key]);
    }

    /**
     * @return Pair<string, T>
     */
    public function last(): Pair
    {
        $key = array_key_last($this->map);
        if (!is_string($key)) {
            throw new BadMethodCallException('Cannot grab last element from an empty map');
        }

        return new Pair($key, $this->map[$key]);
    }

    /**
     * @return Pair<string, T>
     */
    public function skip(int $position): ?Pair
    {
        $keys = $this->keys();

        if (array_key_exists($position, $keys)) {
            $key = $keys[$position];

            return new Pair($key, $this->map[$key]);
        }

        return null;
    }

    /**
     * @param iterable<string, T> $values
     *
     * @return CypherMap<T>
     */
    public function merge(iterable $values): CypherMap
    {
        $tbr = $this->map;

        foreach ($values as $key => $value) {
            $tbr[$key] = $value;
        }

        return new self($tbr);
    }

    /**
     * @param iterable<string, T> $map
     *
     * @return CypherMap<T>
     */
    public function intersect(iterable $map): CypherMap
    {
        $tbr = [];
        foreach ($map as $key => $value) {
            if (array_key_exists($key, $this->map)) {
                $tbr[$key] = $this->map[$key];
            }
        }

        return new self($tbr);
    }

    /**
     * @param iterable<string, T> $map
     *
     * @return CypherMap<T>
     */
    public function diff($map): CypherMap
    {
        $tbr = $this->map;
        /** @psalm-suppress UnusedForeachValue */
        foreach ($map as $key => $value) {
            if (array_key_exists($key, $tbr)) {
                unset($tbr[$key]);
            }
        }

        return new self($tbr);
    }

    public function hasKey(string $key): bool
    {
        return array_key_exists($key, $this->map);
    }

    /**
     * @param T $value
     */
    public function hasValue($value): bool
    {
        return in_array($value, $this->map, true);
    }

    /**
     * @param callable(string, T):bool $callback
     *
     * @return CypherMap<T>
     */
    public function filter(callable $callback): CypherMap
    {
        $tbr = [];
        foreach ($this->map as $key => $value) {
            if ($callback($key, $value)) {
                $tbr[$key] = $value;
            }
        }

        return new self($tbr);
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
        if (func_num_args() === 2) {
            return $this->map[$key] ?? $default;
        }

        if (!array_key_exists($key, $this->map)) {
            throw new OutOfBoundsException();
        }

        return $this->map[$key];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->map);
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
        $tbr = [];
        foreach ($this->map as $key => $value) {
            $tbr[$key] = $callback($key, $value);
        }

        return new self($tbr);
    }

    /**
     * @return array<Pair<string, T>>
     */
    public function pairs(): array
    {
        $tbr = [];
        foreach ($this->map as $key => $value) {
            $tbr[] = new Pair($key, $value);
        }

        return $tbr;
    }

    /**
     * @template TInitial
     *
     * @param callable(TInitial|null, string, T):TInitial $callback
     * @param TInitial|null                               $initial
     *
     * @return TInitial
     */
    public function reduce(callable $callback, $initial = null)
    {
        foreach ($this->map as $key => $value) {
            $initial = $callback($initial, $key, $value);
        }

        return $initial;
    }

    /**
     * @return CypherMap<T>
     */
    public function reversed(): CypherMap
    {
        return new self(array_reverse($this->map, true));
    }

    /**
     * @return CypherMap<T>
     */
    public function slice(int $offset, int $length = null): CypherMap
    {
        return new self(array_slice($this->map, $offset, $length, true));
    }

    /**
     * @param (callable(T, T):int)|null $comparator
     *
     * @return CypherMap<T>
     */
    public function sorted(?callable $comparator = null): CypherMap
    {
        $tbr = $this->map;
        if ($comparator === null) {
            sort($tbr);
        } else {
            /** @psalm-suppress ImpureFunctionCall */
            usort($tbr, $comparator);
        }

        /** @var array<string, T> $tbr */
        return new self($tbr);
    }

    /**
     * @param (callable(string, string):int)|null $comparator
     *
     * @return CypherMap<T>
     */
    public function ksorted(callable $comparator = null): CypherMap
    {
        $tbr = $this->map;
        if ($comparator === null) {
            ksort($tbr);
        } else {
            /** @psalm-suppress ImpureFunctionCall */
            uksort($tbr, $comparator);
        }

        /** @var array<string, T> $tbr */
        return new self($tbr);
    }

    /**
     * @return float|int
     */
    public function sum()
    {
        $first = $this->map[array_key_first($this->map) ?? ''] ?? null;
        if (!is_int($first) && !is_float($first)) {
            return 0;
        }

        return array_sum($this->map);
    }

    /**
     * @return list<T>
     */
    public function values(): array
    {
        return array_values($this->map);
    }

    /**
     * @param iterable<string, T> $map
     *
     * @return CypherMap<T>
     */
    public function union(iterable $map): CypherMap
    {
        $tbr = $this->map;
        foreach ($map as $key => $value) {
            $tbr[$key] = $value;
        }

        return new self($tbr);
    }

    /**
     * @param iterable<string, T> $map
     *
     * @return CypherMap<T>
     */
    public function xor(iterable $map): CypherMap
    {
        $tbr = [];
        foreach ($map as $key => $value) {
            if (!array_key_exists($key, $this->map)) {
                $tbr[$key] = $value;
            }
        }

        $cypherMap = new self($map);
        foreach ($this->map as $key => $value) {
            if (!array_key_exists($key, $cypherMap->map)) {
                $tbr[$key] = $value;
            }
        }

        return new self($tbr);
    }

    public function __debugInfo()
    {
        return $this->map;
    }
}
