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
use ArrayAccess;
use BadMethodCallException;
use function call_user_func;
use function count;
use Countable;
use Generator;
use function implode;
use const INF;
use function is_array;
use function is_callable;
use function is_object;
use Iterator;
use function iterator_to_array;
use JsonSerializable;
use const PHP_INT_MAX;
use function property_exists;
use function sprintf;
use function usort;

/**
 * Abstract immutable sequence with basic functional methods.
 *
 * @template TValue
 * @template TKey of array-key
 *
 * @implements ArrayAccess<TKey, TValue>
 * @implements Iterator<TKey, TValue>
 */
abstract class AbstractCypherSequence implements Countable, JsonSerializable, ArrayAccess, Iterator
{
    /** @var list<TKey> */
    protected array $keyCache = [];
    /** @var array<TKey, TValue> */
    protected array $cache = [];
    private int $cacheLimit = PHP_INT_MAX;
    protected int $currentPosition = 0;
    private int $generatorPosition = 0;

    /**
     * @var (callable():(Generator<TKey, TValue>))|Generator<TKey, TValue>
     */
    protected $generator;

    /**
     * @template Value
     *
     * @param callable():(Generator<mixed, Value>) $operation
     *
     * @return static<Value, TKey>
     *
     * @psalm-mutation-free
     */
    abstract protected function withOperation($operation): self;

    /**
     * Copies the sequence.
     *
     * @return static<TValue, TKey>
     *
     * @psalm-mutation-free
     */
    final public function copy(): self
    {
        return $this->withOperation(function () {
            yield from $this;
        });
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
     * Creates a new sequence by merging this one with the provided iterable. When the iterable is not a list, the provided values will override the existing items in case of a key collision.
     *
     * @template NewValue
     *
     * @param iterable<mixed, NewValue> $values
     *
     * @return static<TValue|NewValue, array-key>
     *
     * @psalm-mutation-free
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
     *
     * @psalm-mutation-free
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
     *
     * @psalm-mutation-free
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
     *
     * @psalm-mutation-free
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
     *
     * @psalm-mutation-free
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
     *
     * @psalm-mutation-free
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
     *
     * @psalm-mutation-free
     */
    public function keyBy(string $key): ArrayList
    {
        return new ArrayList(function () use ($key) {
            foreach ($this as $value) {
                if (is_array($value) && array_key_exists($key, $value)) {
                    yield $value[$key];
                } elseif (is_object($value) && property_exists($value, $key)) {
                    yield $value->$key;
                }
            }
        });
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

    public function offsetGet($offset)
    {
        while (!array_key_exists($offset, $this->cache) && !$this->valid()) {
            $this->next();
        }

        if (array_key_exists($offset, $this->cache)) {
            throw new BadMethodCallException(sprintf('Cannot get item at position: "%s" for sequence %s', $offset, static::class));
        }

        return $this->cache[$offset];
    }

    public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException(sprintf('%s is immutable', static::class));
    }

    public function offsetUnset($offset)
    {
        throw new BadMethodCallException(sprintf('%s is immutable', static::class));
    }

    /**
     * @param TKey $offset
     *
     * @psalm-suppress UnusedForeachValue
     */
    public function offsetExists($offset): bool
    {
        while (!array_key_exists($offset, $this->cache) && !$this->valid()) {
            $this->next();
        }

        return array_key_exists($offset, $this->cache);
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Returns the sequence as an array.
     *
     * @return array<TKey, TValue>
     */
    final public function toArray(): array
    {
        while (!$this->valid()) {
            $this->next();
        }

        return $this->cache;
    }

    final public function count(): int
    {
        return count($this->toArray());
    }

    public function current()
    {
        if ($this->cache === []) {
            $generator = $this->getGenerator();
            $this->cache[$generator->key()] = $generator->current();
            $this->keyCache[] = $generator->key();
        }

        return $this->cache[$this->cacheKey()];
    }

    public function valid(): bool
    {
        return $this->currentPosition < $this->generatorPosition || $this->getGenerator()->valid();
    }

    public function rewind(): void
    {
        $this->currentPosition = max(
            $this->currentPosition - $this->cacheLimit,
            0
        );
    }

    public function next(): void
    {
        $generator = $this->getGenerator();
        if ($this->currentPosition === $this->generatorPosition && $generator->valid()) {
            $generator->next();

            $this->keyCache[] = $generator->key();
            $this->cache[$generator->key()] = $generator->current();
            ++$this->generatorPosition;
        }
        ++$this->currentPosition;
    }

    /**
     * @return TKey
     */
    protected function cacheKey()
    {
        return $this->keyCache[$this->currentPosition % $this->cacheLimit];
    }

    /**
     * @return Iterator<TKey, TValue>
     */
    public function getGenerator(): Iterator
    {
        if (is_callable($this->generator)) {
            $this->generator = call_user_func($this->generator);
        }

        return $this->generator;
    }

    public function withCacheLimit(int $cacheLimit): self
    {
        $tbr = $this->copy();
        $tbr->cacheLimit = $cacheLimit;

        return $tbr;
    }
}
