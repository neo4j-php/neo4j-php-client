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

use function array_filter;
use function array_key_last;
use function array_map;
use function array_reduce;
use function array_search;
use function array_slice;
use function array_sum;
use ArrayIterator;
use BadMethodCallException;
use function count;
use function in_array;
use function is_int;
use Laudis\Neo4j\Contracts\CypherContainerInterface;
use OutOfBoundsException;
use function sort;
use function usort;

/**
 * @template T
 *
 * @implements CypherContainerInterface<int, T>
 */
final class CypherList implements CypherContainerInterface
{
    /** @var list<T> */
    private array $array;

    /**
     * @param iterable<T> $array
     */
    public function __construct(iterable $array)
    {
        if ($array instanceof self) {
            $this->array = $array->array;
        } else {
            $this->array = [];
            foreach ($array as $value) {
                $this->array[] = $value;
            }
        }
    }

    public function count(): int
    {
        return count($this->array);
    }

    /**
     * @return CypherList<T>
     */
    public function copy(): CypherList
    {
        $tbr = $this->array;

        return new CypherList($tbr);
    }

    public function isEmpty(): bool
    {
        return count($this->array) === 0;
    }

    /**
     * @return list<T>
     */
    public function toArray(): array
    {
        return $this->array;
    }

    public function getIterator()
    {
        return new ArrayIterator($this->array);
    }

    public function offsetExists($offset): bool
    {
        return isset($this->array[$offset]);
    }

    /**
     * @psalm-suppress InvalidReturnType
     *
     * @param int $offset
     *
     * @return T
     */
    public function offsetGet($offset)
    {
        return $this->array[$offset];
    }

    /**
     * @param int $offset
     * @param T   $value
     */
    public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException('A cypher list is immutable');
    }

    /**
     * @param int $offset
     */
    public function offsetUnset($offset)
    {
        throw new BadMethodCallException('A cypher list is immutable');
    }

    /**
     * @param T ...$values
     *
     * @deprecated Use hasValue instead
     */
    public function contains(...$values): bool
    {
        foreach ($values as $value) {
            if (!in_array($value, $this->array, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param T $value
     */
    public function hasValue($value): bool
    {
        return in_array($value, $this->array, true);
    }

    /**
     * @param (callable(T):bool) $callback
     *
     * @return CypherList<T>
     */
    public function filter(callable $callback): CypherList
    {
        return new CypherList(array_filter($this->array, $callback));
    }

    /**
     * @param T $value
     *
     * @return false|int
     */
    public function find($value)
    {
        return array_search($value, $this->array, true);
    }

    /**
     * @return T
     */
    public function first()
    {
        if (!isset($this->array[0])) {
            throw new OutOfBoundsException('Cannot grab first element of an empty list');
        }

        return $this->array[0];
    }

    /**
     * @return T
     */
    public function get(int $index)
    {
        return $this->array[$index];
    }

    public function join(?string $glue = null): string
    {
        /** @psalm-suppress MixedArgumentTypeCoercion */
        return implode($glue ?? '', $this->array);
    }

    /**
     * @return T
     */
    public function last()
    {
        $key = array_key_last($this->array);
        if (!is_int($key)) {
            throw new BadMethodCallException('Cannot grab last element from an empty list');
        }

        return $this->array[$key];
    }

    /**
     * @template U
     *
     * @param callable(T):U $callback
     *
     * @return CypherList<U>
     */
    public function map(callable $callback): CypherList
    {
        return new CypherList(array_map($callback, $this->array));
    }

    /**
     * @param iterable<T> $values
     *
     * @return CypherList<T>
     */
    public function merge($values): CypherList
    {
        $tbr = $this->array;
        foreach ($values as $value) {
            $tbr[] = $value;
        }

        return new CypherList($tbr);
    }

    /**
     * @template U
     *
     * @param callable(U|null, T):U $callback
     * @param U|null                $initial
     *
     * @return U|null
     */
    public function reduce(callable $callback, $initial = null)
    {
        /** @var U|null */
        return array_reduce($this->array, $callback, $initial);
    }

    /**
     * @return CypherList<T>
     */
    public function reversed(): CypherList
    {
        return new CypherList(array_reverse($this->array));
    }

    public function slice(int $index, int $length = null): CypherList
    {
        return new CypherList(array_slice($this->array, $index, $length));
    }

    /**
     * @param (callable(T,T):int)|null $comparator
     *
     * @return CypherList<T>
     */
    public function sorted(callable $comparator = null): CypherList
    {
        $tbr = $this->array;
        if ($comparator === null) {
            sort($tbr);
        } else {
            usort($tbr, $comparator);
        }

        return new CypherList($tbr);
    }

    /**
     * @return float|int
     */
    public function sum()
    {
        return array_sum($this->array);
    }

    /**
     * @return array<int, T>
     */
    public function jsonSerialize(): array
    {
        /** @var array<int, T> */
        return $this->array;
    }

    /**
     * @return list<T>
     */
    public function __debugInfo()
    {
        return $this->array;
    }
}
