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
use function count;
use Countable;
use function implode;
use function is_array;
use function is_object;
use function iterator_to_array;
use function property_exists;
use const INF;

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
    /** @var (\ArrayAccess<TKey, TValue>&\Countable&\Traversable<TKey, TValue>)|array<TKey, TValue> */
    protected $sequence = [];

    /**
     * @template Value
     *
     * @param iterable<Value> $iterable
     *
     * @return static<Value, array-key>
     */
    abstract protected function withIterable(iterable $iterable): AbstractCypherSequence;

    final public function count(): int
    {
        return count($this->sequence);
    }

    /**
     * Copies the sequence.
     *
     * @return static
     */
    final public function copy(): self
    {
        // Make sure the sequence is actually copied by reassigning it.
        $map = $this->sequence;

        return $this->withIterable($map);
    }

    /**
     * Returns whether the sequence is empty.
     */
    final public function isEmpty(): bool
    {
        return count($this->sequence) === 0;
    }

    /**
     * Returns the sequence as an array.
     *
     * @return array<TKey, TValue>
     */
    final public function toArray(): array
    {
        if (is_array($this->sequence)) {
            return $this->sequence;
        }

        return iterator_to_array($this->sequence, true);
    }

    /**
     * Creates a new sequence by merging this one with the provided iterable. The provided values will override the existing items in case of a key collision.
     *
     * @param iterable<array-key, TValue> $values
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
        if (is_array($this->sequence)) {
            return array_key_exists($key, $this->sequence);
        }

        return $this->sequence->offsetExists($key);
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
        /** @var array<TKey, TValue> $tbr */
        $tbr = [];
        foreach ($this->sequence as $key => $value) {
            /** @psalm-suppress ImpureFunctionCall */
            if ($callback($value, $key)) {
                $tbr[$key] = $value;
            }
        }

        return $this->withIterable($tbr);
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
        /** @var array<TKey, ReturnType> $tbr */
        $tbr = [];
        foreach ($this->sequence as $key => $value) {
            /** @psalm-suppress ImpureFunctionCall */
            $tbr[$key] = $callback($value, $key);
        }

        return $this->withIterable($tbr);
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
        foreach ($this->sequence as $key => $value) {
            /** @psalm-suppress ImpureFunctionCall */
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
        foreach ($this->sequence as $i => $x) {
            if ($value === $x) {
                return $i;
            }
        }

        return false;
    }

    /**
     * Creates a reversed sequence.
     *
     * @return static
     */
    abstract public function reversed(): self;

    /**
     * Slices a new sequence starting from the given offset with a certain length.
     * If the length is null it will slice the entire remainder starting from the offset.
     *
     * @return static
     */
    public function slice(int $offset, int $length = null): self
    {
        $i = 0;
        $length ??= INF;
        $tbr = [];
        foreach ($this->sequence as $key => $value) {
            if ($length === 0) {
                return $this->withIterable($tbr);
            }
            if ($i === $offset) {
                --$length;
                $tbr[$key] = $value;
            } else {
                ++$i;
            }
        }

        return $this->withIterable($tbr);
    }

    /**
     * Creates a sorted sequence. If the compoarator is null it will use natural ordering.
     *
     * @param (callable(TValue, TValue):int)|null $comparator
     *
     * @return static
     */
    abstract public function sorted(?callable $comparator = null): self;

    /**
     * Creates a list from the arrays and objects in the sequence whose values corresponding with the provided key.
     */
    public function keyBy(string $key): ArrayList
    {
        $tbr = [];
        foreach ($this->sequence as $value) {
            if (is_array($value) && array_key_exists($key, $value)) {
                /** @var mixed */
                $tbr[] = $value[$key];
            } elseif (is_object($value) && property_exists($value, $key)) {
                /** @var mixed */
                $tbr[] = $value->$key;
            }
        }

        return ArrayList::fromIterable($tbr);
    }

    /**
     * Joins the values within the sequence together with the provided glue. If the glue is null, it will be an empty string.
     */
    public function join(?string $glue = null): string
    {
        /** @psalm-suppress MixedArgumentTypeCoercion */
        return implode($glue ?? '', $this->sequence);
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
        foreach ($this->sequence as $key => $value) {
            /** @psalm-suppress ImpureFunctionCall */
            $callable($value, $key);
        }

        return $this;
    }
}
