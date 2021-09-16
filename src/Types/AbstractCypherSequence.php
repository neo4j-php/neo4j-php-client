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
use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use function count;
use Countable;
use function in_array;
use IteratorAggregate;
use JsonSerializable;

/**
 * @template TKey of array-key
 * @template TValue
 *
 * @implements ArrayAccess<TKey, TValue>
 * @implements ArrayAccess<TKey, TValue>
 *
 * @psalm-immutable
 */
abstract class AbstractCypherSequence implements Countable, JsonSerializable, ArrayAccess, IteratorAggregate
{
    /** @var array<TKey, TValue> */
    protected array $sequence;

    /**
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
     * @return static
     */
    final public function copy(): self
    {
        $map = $this->sequence;

        return $this::fromIterable($map);
    }

    final public function isEmpty(): bool
    {
        return count($this->sequence) === 0;
    }

    /**
     * @return array<TKey, TValue>
     */
    final public function toArray(): array
    {
        return $this->sequence;
    }

    /**
     * @return ArrayIterator<TKey, TValue>
     */
    final public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->sequence);
    }

    /**
     * @param TKey $offset
     */
    final public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->sequence);
    }

    /**
     * @param TKey $offset
     *
     * @return TValue
     */
    final public function offsetGet($offset)
    {
        return $this->sequence[$offset];
    }

    /**
     * @param TKey   $offset
     * @param TValue $value
     */
    final public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException(static::class.' is immutable');
    }

    /**
     * @param string $offset
     */
    final public function offsetUnset($offset)
    {
        throw new BadMethodCallException(static::class.' is immutable');
    }

    final public function jsonSerialize(): array
    {
        return $this->sequence;
    }

    /**
     * @param iterable<array-key, TValue> $values
     *
     * @return static
     */
    abstract public function merge(iterable $values): self;

    /**
     * @param TKey $key
     */
    final public function hasKey($key): bool
    {
        return array_key_exists($key, $this->sequence);
    }

    /**
     * @param TValue $value
     */
    final public function hasValue($value): bool
    {
        return in_array($value, $this->sequence, true);
    }

    /**
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
     * @param TValue $value
     *
     * @return false|TKey
     */
    final public function find($value)
    {
        return array_search($value, $this->sequence, true);
    }

    /**
     * @return static
     */
    abstract public function reversed(): self;

    /**
     * @return static
     */
    abstract public function slice(int $offset, int $length = null): self;

    /**
     * @param (pure-callable(TValue, TValue):int)|null $comparator
     *
     * @return static
     */
    abstract public function sorted(?callable $comparator = null): self;

    final public function __debugInfo()
    {
        return $this->sequence;
    }
}
