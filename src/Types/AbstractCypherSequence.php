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
use function array_search;
use function count;
use Countable;
use function implode;
use function in_array;

/**
 * Abstract immutable sequence with basic functional methods.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @extends AbstractCypherObject<TKey, TValue>
 *
 * @psalm-immutable
 */
abstract class AbstractCypherSequence extends AbstractCypherObject implements Countable
{
    /** @var array<TKey, TValue> */
    protected array $sequence;

    /**
     * Creates a new instance from the given iterable.
     *
     * @template Value
     *
     * @param iterable<Value> $iterable
     *
     * @return static
     *
     * @pure
     */
    abstract public static function fromIterable(iterable $iterable): self;

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

        return $this::fromIterable($map);
    }

    /**
     * Returns whether or not the sequence is empty.
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
        return $this->sequence;
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
        return array_key_exists($key, $this->sequence);
    }

    /**
     * Checks if the sequence contains the given value. The equality check is strict.
     *
     * @param TValue $value
     */
    final public function hasValue($value): bool
    {
        return in_array($value, $this->sequence, true);
    }

    /**
     * Creates a filtered the sequence with the provided callback.
     *
     * @param pure-callable(TKey, TValue):bool $callback
     *
     * @return static
     */
    final public function filter(callable $callback): self
    {
        $tbr = [];
        foreach ($this->sequence as $key => $value) {
            if ($callback($key, $value)) {
                $tbr[$key] = $value;
            }
        }

        return $this::fromIterable($tbr);
    }

    /**
     * Maps the values of this sequence to a new one with the provided callback.
     *
     * @template U
     *
     * @param pure-callable(TKey, TValue):U $callback
     *
     * @return static
     */
    final public function map(callable $callback): self
    {
        $tbr = [];
        foreach ($this->sequence as $key => $value) {
            $tbr[$key] = $callback($key, $value);
        }

        return $this::fromIterable($tbr);
    }

    /**
     * Reduces this sequence with the given callback.
     *
     * @template TInitial
     *
     * @param pure-callable(TInitial|null, TKey, TValue):TInitial $callback
     * @param TInitial|null                                       $initial
     *
     * @return TInitial
     */
    final public function reduce(callable $callback, $initial = null)
    {
        foreach ($this->sequence as $key => $value) {
            $initial = $callback($initial, $key, $value);
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
        return array_search($value, $this->sequence, true);
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
    abstract public function slice(int $offset, int $length = null): self;

    /**
     * Creates a sorted sequence. If the compoarator is null it will use natural ordering.
     *
     * @param (pure-callable(TValue, TValue):int)|null $comparator
     *
     * @return static
     */
    abstract public function sorted(?callable $comparator = null): self;

    /**
     * Joins the values within the sequence together with the provided glue. If the glue is null, it will be an empty string.
     */
    public function join(?string $glue = null): string
    {
        /** @psalm-suppress MixedArgumentTypeCoercion */
        return implode($glue ?? '', $this->sequence);
    }
}
