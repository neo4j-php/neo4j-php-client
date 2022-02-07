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
use function array_slice;
use function call_user_func;
use function count;
use Countable;
use Generator;
use function implode;
use function is_a;
use const INF;
use function is_array;
use function is_object;
use function property_exists;

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
     * @var (\ArrayAccess<OriginalKey, OriginalValue>&\Countable&\Traversable<OriginalKey, OriginalValue>)|array<OriginalKey, OriginalValue>
     */
    protected $sequence = [];

    /**
     * @var callable(OriginalValue, OriginalKey):array{0: TKey, 1: TValue}
     */
    private $typeTransformation;

    /** @var list<
     *     array{type: 'filter', fn: callable(TValue, TKey): bool},
     *     array{type: 'copy'},
     *     array{type: 'sort', fn:(callable(TValue, TValue):int)|null}
     * >
     */
    private $transformations = [];

    final public function count(): int
    {
        return count($this->sequence);
    }

    /**
     * @template Value
     * @template Key of array-key
     *
     * @param callable(TValue, TKey): array{0: Key, 1: Value} $operation
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
        return $this->withOperation(static function ($value, $key) {
            return [$key, $value];
        });
    }

    public function getIterator(): Generator
    {
        foreach ($this->sequence as $key => $value) {
            [$transformedKey, $transformedValue] = call_user_func($this->typeTransformation, $value, $key);

            foreach ($this->transformations as $transformation) {
                if ($transformation['type'] === 'filter' && !$transformation['fn']($transformedValue)) {
                    continue 2;
                }
            }
            yield $transformedKey => $transformedValue;
        }
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
        $tbr = [];
        foreach ($this as $key => $value) {
            $tbr[$key] = $value;
        }

        return $tbr;
    }

    /**
     * Creates a new sequence by merging this one with the provided iterable. When the iterable is not a list, the provided values will override the existing items in case of a key collision.
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
        return $this->offsetExists($key);
    }

    /**
     * @param TKey $offset
     */
    public function offsetExists($offset): bool
    {
        if (is_array($this->sequence)) {
            return array_key_exists($offset, $this->sequence);
        }

        return $this->sequence->offsetExists($offset);
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
     * @return static<TValue, TKey, OriginalValue, OriginalKey>
     */
    final public function filter(callable $callback): self
    {
        $this->transformations[] = ['type' => 'filter', 'fn' => $callback];

        return $this;
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
        return $this->withOperation(static function ($value, $key) use ($callback) {
            return [$key, $callback($value, $key)];
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
        return $this->withArray(array_reverse($this->toArray()));
    }

    /**
     * @template Key of array-key
     * @template Value
     *
     * @param array<Key, Value> $array
     *
     * @return static<Value, Key, Value, Key>
     */
    protected function withArray(array $array): self
    {
        $tbr = $this->withOperation(static fn ($x, $y) => [$y, $x]);

        $tbr->sequence = $array;

        return $tbr;
    }

    /**
     * Slices a new sequence starting from the given offset with a certain length.
     * If the length is null it will slice the entire remainder starting from the offset.
     *
     * @return static
     */
    public function slice(int $offset, int $length = null): self
    {
        if (is_array($this->sequence)) {
            return $this->withArray(array_slice($this->sequence, $offset, $length, true));
        }

        $count = -1;
        $length ??= INF;
        return $this->filter(static function () use ($offset, $length, &$count) {
            ++$count;
            if ($count >= $offset) {
                return ($count + $offset) < $length;
            }

            return false;
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
        if (is_array())
    }

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
