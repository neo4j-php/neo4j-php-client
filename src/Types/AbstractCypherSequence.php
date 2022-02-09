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
use function array_reverse;
use function call_user_func;
use Countable;
use Generator;
use function implode;
use const INF;
use function is_array;
use function is_object;
use function iterator_to_array;
use function property_exists;
use function usort;

/**
 * Abstract immutable sequence with basic functional methods.
 *
 * @template TValue
 * @template TKey of array-key
 *
 * @extends AbstractCypherObject<TKey, TValue>
 */
abstract class AbstractCypherSequence extends AbstractCypherObject implements Countable
{
    /**
     * @var array<TKey, TValue>
     */
    protected ?array $cache = null;

    /**
     * @var callable():(Generator<TKey, TValue>)
     */
    protected $generator;

    final public function count(): int
    {
        return count($this->toArray());
    }

    /**
     * @template Value
     *
     * @param callable():(Generator<mixed, Value>) $operation
     *
     * @return static<Value, TKey>
     */
    abstract protected function withOperation($operation): self;

    /**
     * Copies the sequence.
     *
     * @return static<TValue, TKey>
     */
    final public function copy(): self
    {
        return $this->withOperation(function () {
            yield from $this;
        });
    }

    public function getIterator(): Generator
    {
        yield from call_user_func($this->generator);
    }

    /**
     * Returns whether the sequence is empty.
     *
     * @psalm-suppress UnusedForeachValue
     */
    final public function isEmpty(): bool
    {
        /** @noinspection PhpLoopNeverIteratesInspection */
        foreach ($this as $ignored) {
            return false;
        }

        return true;
    }

    /**
     * Returns the sequence as an array.
     *
     * @return array<TKey, TValue>
     */
    final public function toArray(): array
    {
        $this->cache ??= iterator_to_array($this, true);

        return $this->cache;
    }

    /**
     * Creates a new sequence by merging this one with the provided iterable. When the iterable is not a list, the provided values will override the existing items in case of a key collision.
     *
     * @template NewValue
     *
     * @param iterable<mixed, NewValue> $values
     *
     * @return static<TValue|NewValue, array-key>
     */
    abstract public function merge(iterable $values): self;

    /**
     * Checks if the sequence contains the given key.
     *
     * @param TKey $key
     */
    final public function hasKey($key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * @param TKey $offset
     *
     * @psalm-suppress UnusedForeachValue
     */
    public function offsetExists($offset): bool
    {
        foreach ($this as $key => $value) {
            if ($key === $offset) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if the sequence contains the given value. The equality check is strict.
     *
     * @param TValue $value
     */
    final public function hasValue($value): bool
    {
        return $this->find($value) !== false;
    }

    /**
     * Creates a filtered the sequence with the provided callback.
     *
     * @param callable(TValue, TKey):bool $callback
     *
     * @return static<TValue, TKey>
     */
    final public function filter(callable $callback): self
    {
        return $this->withOperation(function () use ($callback) {
            foreach ($this as $key => $value) {
                if ($callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Maps the values of this sequence to a new one with the provided callback.
     *
     * @template ReturnType
     *
     * @param callable(TValue, TKey):ReturnType $callback
     *
     * @return static<ReturnType, TKey>
     */
    final public function map(callable $callback): self
    {
        return $this->withOperation(function () use ($callback) {
            foreach ($this as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * Reduces this sequence with the given callback.
     *
     * @template TInitial
     *
     * @param callable(TInitial|null, TValue, TKey):TInitial $callback
     * @param TInitial|null                                  $initial
     *
     * @return TInitial
     */
    final public function reduce(callable $callback, $initial = null)
    {
        foreach ($this as $key => $value) {
            $initial = $callback($initial, $value, $key);
        }

        return $initial;
    }

    /**
     * Finds the position of the value within the sequence.
     *
     * @param TValue $value
     *
     * @return false|TKey returns the key of the value if it is found, false otherwise
     */
    final public function find($value)
    {
        foreach ($this as $i => $x) {
            if ($value === $x) {
                return $i;
            }
        }

        return false;
    }

    /**
     * Creates a reversed sequence.
     *
     * @return static<TValue, TKey>
     */
    public function reversed(): self
    {
        return $this->withOperation(function () {
            yield from array_reverse($this->toArray());
        });
    }

    /**
     * Slices a new sequence starting from the given offset with a certain length.
     * If the length is null it will slice the entire remainder starting from the offset.
     *
     * @return static<TValue, TKey>
     */
    public function slice(int $offset, int $length = null): self
    {
        return $this->withOperation(function () use ($offset, $length) {
            $count = 0;
            $length ??= INF;
            foreach ($this as $key => $value) {
                if ($count < $offset) {
                    continue;
                } else {
                    yield $key => $value;
                }
                if ($count === ($offset + $length)) {
                    break;
                }
                ++$count;
            }
        });
    }

    /**
     * Creates a sorted sequence. If the comparator is null it will use natural ordering.
     *
     * @param (callable(TValue, TValue):int)|null $comparator
     *
     * @return static<TValue, TKey>
     */
    public function sorted(?callable $comparator = null): self
    {
        return $this->withOperation(function () use ($comparator) {
            $iterable = $this->toArray();

            if ($comparator) {
                usort($iterable, $comparator);
            } else {
                sort($iterable);
            }

            yield from $iterable;
        });
    }

    /**
     * Creates a list from the arrays and objects in the sequence whose values corresponding with the provided key.
     *
     * @return ArrayList<mixed>
     */
    public function keyBy(string $key): ArrayList
    {
        return ArrayList::fromIterable((function () use ($key) {
            foreach ($this as $value) {
                if (is_array($value) && array_key_exists($key, $value)) {
                    yield $value[$key];
                } elseif (is_object($value) && property_exists($value, $key)) {
                    yield $value->$key;
                }
            }
        })());
    }

    /**
     * Joins the values within the sequence together with the provided glue. If the glue is null, it will be an empty string.
     */
    public function join(?string $glue = null): string
    {
        /** @psalm-suppress MixedArgumentTypeCoercion */
        return implode($glue ?? '', $this->toArray());
    }

    /**
     * Iterates over the sequence and applies the callable.
     *
     * @param callable(TValue, TKey):void $callable
     *
     * @return static<TValue, TKey>
     */
    public function each(callable $callable): self
    {
        foreach ($this as $key => $value) {
            $callable($value, $key);
        }

        return $this;
    }

    public function __debugInfo(): array
    {
        return iterator_to_array($this, true);
    }
}
