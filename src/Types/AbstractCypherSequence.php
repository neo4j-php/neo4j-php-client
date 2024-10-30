<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Types;

use function array_key_exists;
use function array_reverse;

use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;

use function call_user_func;
use function count;

use Countable;
use Generator;

use function get_object_vars;
use function implode;

use const INF;

use function is_array;
use function is_callable;
use function is_numeric;
use function is_object;
use function is_string;

use Iterator;
use JsonSerializable;

use function method_exists;

use OutOfBoundsException;

use const PHP_INT_MAX;

use function property_exists;
use function sprintf;

use UnexpectedValueException;

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
    protected int $generatorPosition = 0;

    /**
     * @var (callable():(Iterator<TKey, TValue>))|Iterator<TKey, TValue>
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
    abstract protected function withOperation(callable $operation): self;

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
     * mixed
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
     * @param TInitial|null                                  $initial
     * @param callable(TInitial|null, TValue, TKey):TInitial $callback
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
    public function slice(int $offset, ?int $length = null): self
    {
        return $this->withOperation(function () use ($offset, $length) {
            if ($length !== 0) {
                $count = -1;
                $length ??= INF;
                foreach ($this as $key => $value) {
                    ++$count;
                    if ($count < $offset) {
                        continue;
                    }

                    yield $key => $value;
                    if ($count === ($offset + $length - 1)) {
                        break;
                    }
                }
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

            if ($comparator !== null) {
                uasort($iterable, $comparator);
            } else {
                asort($iterable);
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
     *
     * @psalm-suppress MixedArrayAccess
     */
    public function pluck(string $key): ArrayList
    {
        return new ArrayList(function () use ($key) {
            foreach ($this as $value) {
                if ((is_array($value) && array_key_exists($key, $value)) || ($value instanceof ArrayAccess && $value->offsetExists($key))) {
                    yield $value[$key];
                } elseif (is_object($value) && property_exists($value, $key)) {
                    yield $value->$key;
                }
            }
        });
    }

    /**
     * Uses the values found at the provided key as the key for the new Map.
     *
     * @return Map<mixed>
     *
     * @psalm-mutation-free
     *
     * @psalm-suppress MixedArrayAccess
     */
    public function keyBy(string $key): Map
    {
        return new Map(function () use ($key) {
            foreach ($this as $value) {
                if (((is_array($value) && array_key_exists($key, $value)) || ($value instanceof ArrayAccess && $value->offsetExists($key))) && $this->isStringable($value[$key])) {
                    yield $value[$key] => $value;
                } elseif (is_object($value) && property_exists($value, $key) && $this->isStringable($value->$key)) {
                    yield $value->$key => $value;
                } else {
                    throw new UnexpectedValueException('Cannot convert the value to a string');
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

    public function offsetGet(mixed $offset): mixed
    {
        while (!array_key_exists($offset, $this->cache) && $this->valid()) {
            $this->next();
        }

        if (!array_key_exists($offset, $this->cache)) {
            throw new OutOfBoundsException(sprintf('Offset: "%s" does not exists in object of instance: %s', $offset, static::class));
        }

        return $this->cache[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException(sprintf('%s is immutable', static::class));
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException(sprintf('%s is immutable', static::class));
    }

    /**
     * @param TKey $offset
     *
     * @psalm-suppress UnusedForeachValue
     */
    public function offsetExists(mixed $offset): bool
    {
        while (!array_key_exists($offset, $this->cache) && $this->valid()) {
            $this->next();
        }

        return array_key_exists($offset, $this->cache);
    }

    public function jsonSerialize(): mixed
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
        $this->preload();

        return $this->cache;
    }

    /**
     * Returns the sequence as an array.
     *
     * @return array<TKey, TValue|array>
     */
    final public function toRecursiveArray(): array
    {
        return $this->map(static function ($x) {
            if ($x instanceof self) {
                return $x->toRecursiveArray();
            }

            return $x;
        })->toArray();
    }

    final public function count(): int
    {
        return count($this->toArray());
    }

    /**
     * @return TValue
     */
    public function current(): mixed
    {
        $this->setupCache();

        return $this->cache[$this->cacheKey()];
    }

    public function valid(): bool
    {
        return $this->currentPosition < $this->generatorPosition || array_key_exists($this->currentPosition, $this->keyCache) || $this->getGenerator()->valid();
    }

    public function rewind(): void
    {
        if ($this->currentPosition > $this->cacheLimit) {
            throw new BadMethodCallException('Cannot rewind cursor: limit exceeded. In order to increase the amount of prefetched (and consequently cached) rows, increase the fetch limit in the session configuration.');
        }

        $this->currentPosition = 0;
    }

    public function next(): void
    {
        $generator = $this->getGenerator();
        if ($this->cache === []) {
            $this->setupCache();
        } elseif ($this->currentPosition === $this->generatorPosition && $generator->valid()) {
            $generator->next();

            if ($generator->valid()) {
                /** @var TKey */
                $this->keyCache[] = $generator->key();
                $this->cache[$generator->key()] = $generator->current();
            }
            ++$this->generatorPosition;
            ++$this->currentPosition;
        } else {
            ++$this->currentPosition;
        }
    }

    /**
     * @return TKey
     */
    public function key(): mixed
    {
        return $this->cacheKey();
    }

    /**
     * @return TKey
     */
    protected function cacheKey()
    {
        return $this->keyCache[$this->currentPosition % max($this->cacheLimit, 1)];
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

    /**
     * @return static<TValue, TKey>
     */
    public function withCacheLimit(int $cacheLimit): self
    {
        $tbr = $this->copy();
        $tbr->cacheLimit = $cacheLimit;

        return $tbr;
    }

    private function setupCache(): void
    {
        $generator = $this->getGenerator();

        $cacheLimit = $this->cacheLimit === PHP_INT_MAX ? PHP_INT_MAX : $this->cacheLimit + 1;

        if (count($this->keyCache) !== 0 && count($this->cache) !== 0 && count($this->cache) % $cacheLimit === 0) {
            $this->cache = [array_key_last($this->cache) => $this->cache[array_key_last($this->cache)]];
            $this->keyCache = [$this->keyCache[array_key_last($this->keyCache)]];
        }

        if ($this->cache === [] && $generator->valid()) {
            /** @var TKey $key */
            $key = $generator->key();
            $this->cache[$key] = $generator->current();
            $this->keyCache[] = $key;
        }
    }

    /**
     * Preload the lazy evaluation.
     */
    public function preload(): void
    {
        while ($this->valid()) {
            $this->next();
        }
    }

    /**
     * @psalm-mutation-free
     */
    protected function isStringable(mixed $key): bool
    {
        return is_string($key) || is_numeric($key) || (is_object($key) && method_exists($key, '__toString'));
    }

    public function __serialize(): array
    {
        $this->preload();

        $tbr = get_object_vars($this);
        $tbr['generator'] = new ArrayIterator();
        $tbr['currentPosition'] = 0;
        $tbr['generatorPosition'] = 0;

        return $tbr;
    }
}
