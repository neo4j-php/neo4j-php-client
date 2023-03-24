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

use ArrayAccess;
use ArrayIterator;
use BadMethodCallException;
use IteratorAggregate;
use JsonSerializable;
use OutOfBoundsException;

use function sprintf;

use Traversable;

/**
 * Abstract immutable container with basic functionality to integrate easily into the driver ecosystem.
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements ArrayAccess<TKey, TValue>
 * @implements IteratorAggregate<TKey, TValue>
 *
 * @psalm-immutable
 */
abstract class AbstractCypherObject implements JsonSerializable, ArrayAccess, IteratorAggregate
{
    /**
     * Represents the container as an array.
     *
     * @return array<TKey, TValue>
     */
    abstract public function toArray(): array;

    /**
     * @return array<TKey, TValue>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @return Traversable<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->toArray());
    }

    /**
     * @param TKey $offset
     */
    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->toArray());
    }

    /**
     * @param TKey $offset
     *
     * @return TValue
     */
    public function offsetGet(mixed $offset): mixed
    {
        $serialized = $this->toArray();
        if (!array_key_exists($offset, $serialized)) {
            throw new OutOfBoundsException("Offset: \"$offset\" does not exists in object of instance: ".static::class);
        }

        return $serialized[$offset];
    }

    /**
     * @param TKey   $offset
     * @param TValue $value
     */
    final public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new BadMethodCallException(sprintf('%s is immutable', static::class));
    }

    /**
     * @param TKey $offset
     */
    final public function offsetUnset(mixed $offset): void
    {
        throw new BadMethodCallException(sprintf('%s is immutable', static::class));
    }
}
