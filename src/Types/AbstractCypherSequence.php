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
 * @template OriginalValue
 * @template OriginalKey of array-key
 *
 * @extends AbstractCypherObject<TKey, TValue>
 */
abstract class AbstractCypherSequence extends AbstractCypherObject implements Countable
{
    /**
     * @var iterable<OriginalKey, OriginalValue>
     */
    protected iterable $sequence = [];
    /**
     * @var array<TKey, TValue>
     */
    protected ?array $transformation = null;

    /**
     * @var callable(iterable<OriginalKey, OriginalValue>):(Generator<TKey, TValue>)
     */
    protected $typeTransformation;

    final public function count(): int
    {
        return count($this->toArray());
    }

    /**
     * @template Value
     * @template Key of array-key
     *
     * @param callable(iterable<TKey, TValue>):(Generator<Key, Value>) $operation
     *
     * @return static<Value, Key, TValue, TKey>
     */
    abstract protected function withOperation($operation): self;

    /**
     * Copies the sequence.
     *
     * @return static<TValue, TKey, TValue, TKey>
     */
    final public function copy(): self
    {
        return $this->withOperation(static function ($iterable) {
            yield from $iterable;
        });
    }

    public function getIterator(): Generator
    {
        yield from call_user_func($this->typeTransformation, $this->sequence);
    }

    /**
     * Returns whether the sequence is empty.
     *
     * @psalm-suppress UnusedForeachValue
     */
    final public function isEmpty(): bool
    {
        /** @noinspection PhpLoopNeverIteratesInspection */
        foreach (call_user_func($this->typeTransformation, $this->sequence) as $ignored) {
            return true;
        }

        return false;
    }

    /**
     * Returns the sequence as an array.
     *
     * @return array<TKey, TValue>
     */
    final public function toArray(): array
    {
        $this->transformation ??= iterator_to_array(call_user_func($this->typeTransformation, $this->sequence), true);

        return $this->transformation;
    }

    /**
     * Creates a new sequence by merging this one with the provided iterable. When the iterable is not a list, the provided values will override the existing items in case of a key collision.
     *
     * @param iterable<TKey, TValue> $values
     *
     * @return static
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
     * @return static<TValue, TKey, TValue, TKey>
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
     * @return static<ReturnType, TKey, TValue, TKey>
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
     * @return static<TValue, TKey, TValue, TKey>
     */
    public function reversed(): self
    {
        return $this->withOperation(static function ($iterable) {
            $iterable = is_array($iterable) ? $iterable : iterator_to_array($iterable, true);

            yield from array_reverse($iterable);
        });
    }

    /**
     * Slices a new sequence starting from the given offset with a certain length.
     * If the length is null it will slice the entire remainder starting from the offset.
     *
     * @return static
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
     * @return static
     */
    public function sorted(?callable $comparator = null): self
    {
        return $this->withOperation(static function ($iterable) use ($comparator) {
            $iterable = is_array($iterable) ? $iterable : iterator_to_array($iterable, true);

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
     */
    public function keyBy(string $key): ArrayList
    {
        $tbr = ArrayList::fromIterable($this);

        return $tbr->withOperation(static function () use ($key) {
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
     * @return static<TValue, TKey, OriginalValue, OriginalKey>
     */
    public function each(callable $callable): self
    {
        foreach ($this as $key => $value) {
            $callable($value, $key);
        }

        return $this;
    }
}
