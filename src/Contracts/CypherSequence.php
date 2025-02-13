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

namespace Laudis\Neo4j\Contracts;

use Countable;
use Iterator;
use JsonSerializable;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Map;

/**
 * Abstract immutable sequence with basic functional methods.
 *
 * @template TValue
 */
interface CypherSequence extends Countable, JsonSerializable
{
    /**
     * Copies the sequence.
     *
     * @return self<TValue>
     *
     * @psalm-mutation-free
     */
    public function copy(): self;

    /**
     * Returns whether the sequence is empty.
     *
     * @psalm-suppress UnusedForeachValue
     */
    public function isEmpty(): bool;

    /**
     * Creates a new sequence by merging this one with the provided iterable. When the iterable is not a list, the provided values will override the existing items in case of a key collision.
     *
     * @template NewValue
     *
     * @param iterable<mixed, NewValue> $values
     *
     * @return self<TValue|NewValue>
     *
     * @psalm-mutation-free
     */
    public function merge(iterable $values): self;

    /**
     * Checks if the sequence contains the given key.
     */
    public function hasKey(string|int $key): bool;

    /**
     * Checks if the sequence contains the given value. The equality check is strict.
     */
    public function hasValue(mixed $value): bool;

    /**
     * Creates a filtered the sequence with the provided callback.
     *
     * @param callable(TValue, array-key):bool $callback
     *
     * @return self<TValue>
     *
     * @psalm-mutation-free
     */
    public function filter(callable $callback): self;

    /**
     * Maps the values of this sequence to a new one with the provided callback.
     *
     * @template ReturnType
     *
     * @param callable(TValue, array-key):ReturnType $callback
     *
     * @return self<ReturnType>
     *
     * @psalm-mutation-free
     */
    public function map(callable $callback): self;

    /**
     * Reduces this sequence with the given callback.
     *
     * @template TInitial
     *
     * @param TInitial|null                                       $initial
     * @param callable(TInitial|null, TValue, array-key):TInitial $callback
     *
     * @return TInitial
     */
    public function reduce(callable $callback, mixed $initial = null): mixed;

    /**
     * Finds the position of the value within the sequence.
     *
     * @return false|array-key returns the key of the value if it is found, false otherwise
     */
    public function find(mixed $value): false|string|int;

    /**
     * Creates a reversed sequence.
     *
     * @return self<TValue>
     *
     * @psalm-mutation-free
     */
    public function reversed(): self;

    /**
     * Slices a new sequence starting from the given offset with a certain length.
     * If the length is null it will slice the entire remainder starting from the offset.
     *
     * @return self<TValue>
     *
     * @psalm-mutation-free
     */
    public function slice(int $offset, ?int $length = null): self;

    /**
     * Creates a sorted sequence. If the comparator is null it will use natural ordering.
     *
     * @param (callable(TValue, TValue):int)|null $comparator
     *
     * @return self<TValue>
     *
     * @psalm-mutation-free
     */
    public function sorted(?callable $comparator = null): self;

    /**
     * Creates a list from the arrays and objects in the sequence whose values corresponding with the provided key.
     *
     * @return CypherList<mixed>
     *
     * @psalm-mutation-free
     */
    public function pluck(string|int $key): CypherList;

    /**
     * Uses the values found at the provided key as the key for the new Map.
     *
     * @return CypherMap<mixed>
     *
     * @psalm-mutation-free
     */
    public function keyBy(string|int $key): CypherMap;

    /**
     * Joins the values within the sequence together with the provided glue. If the glue is null, it will be an empty string.
     */
    public function join(?string $glue = null): string;

    /**
     * Iterates over the sequence and applies the callable.
     *
     * @param callable(TValue, array-key):void $callable
     *
     * @return self<TValue>
     */
    public function each(callable $callable): self;

    /**
     * Returns the sequence as an array.
     *
     * @return array<array-key, TValue>
     */
    public function toArray(): array;

    /**
     * Returns the sequence as an array.
     *
     * @return array<array-key, TValue|array>
     */
    public function toRecursiveArray(): array;

    /**
     * @return Iterator<array-key, TValue>
     */
    public function getGenerator(): Iterator;

    /**
     * @return self<TValue>
     */
    public function withCacheLimit(int $cacheLimit): self;

    /**
     * Preload the lazy evaluation.
     */
    public function preload(): void;

    public function __serialize(): array;
}
