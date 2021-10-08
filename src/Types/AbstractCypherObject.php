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
use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use function get_class;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use function sprintf;

/**
 * Abstract immutable container with basic functionality to integrate easily into the driver ecosystem.
 *
 * @psalm-immutable
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 */
abstract class AbstractCypherObject implements JsonSerializable, ArrayAccess, IteratorAggregate
{
    /**
     * Represents the container as an array.
     *
     * @return array<TKey, TValue>
     */
    abstract public function toArray(): array;

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return ArrayIterator<TKey, TValue>
     */
    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->toArray());
    }

    /**
     * @param TKey $offset
     */
    final public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->toArray());
    }

    /**
     * @param TKey $offset
     *
     * @return TValue
     */
    final public function offsetGet($offset)
    {
        $serialized = $this->toArray();
        if (!array_key_exists($offset, $serialized)) {
            throw new InvalidArgumentException("Offset: $offset does not exists for class: ".static::class);
        }

        return $serialized[$offset];
    }

    /**
     * @param TKey   $offset
     * @param TValue $value
     */
    final public function offsetSet($offset, $value): void
    {
        throw new BadMethodCallException(sprintf('%s is immutable', get_class($this)));
    }

    /**
     * @param TKey $offset
     */
    final public function offsetUnset($offset): void
    {
        throw new BadMethodCallException(sprintf('%s is immutable', get_class($this)));
    }
}
