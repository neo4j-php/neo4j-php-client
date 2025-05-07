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

use AppendIterator;
use ArrayAccess;
use ArrayIterator;
use Generator;

use function is_array;
use function is_callable;

use Iterator;
use Laudis\Neo4j\Contracts\CypherSequence;
use Laudis\Neo4j\Exception\RuntimeTypeException;
use Laudis\Neo4j\TypeCaster;
use OutOfBoundsException;

/**
 * An immutable ordered sequence of items.
 *
 * @template TValue
 *
 * @implements CypherSequence<TValue>
 * @implements Iterator<int, TValue>
 * @implements ArrayAccess<int, TValue>
 */
class CypherList implements CypherSequence, Iterator, ArrayAccess
{
    /**
     * @use CypherSequenceTrait<TValue>
     */
    use CypherSequenceTrait;

    /**
     * @param iterable<mixed, TValue>|callable():Generator<mixed, TValue> $iterable
     *
     * @psalm-mutation-free
     */
    public function __construct(iterable|callable $iterable = [])
    {
        if (is_array($iterable)) {
            $iterable = new ArrayIterator($iterable);
        }

        $this->generator = static function () use ($iterable): Generator {
            $i = 0;
            /** @var Generator<mixed, TValue> $it */
            $it = is_callable($iterable) ? $iterable() : $iterable;
            foreach ($it as $value) {
                yield $i => $value;
                ++$i;
            }
        };
    }

    /**
     * @template Value
     *
     * @param callable():(Generator<Value>) $operation
     *
     * @return self<Value>
     *
     * @psalm-mutation-free
     */
    protected function withOperation(callable $operation): self
    {
        return new self($operation);
    }

    /**
     * Returns the first element in the sequence.
     *
     * @return TValue
     */
    public function first()
    {
        foreach ($this as $value) {
            return $value;
        }

        throw new OutOfBoundsException('Cannot grab first element of an empty list');
    }

    /**
     * Returns the last element in the sequence.
     *
     * @return TValue
     */
    public function last()
    {
        if ($this->isEmpty()) {
            throw new OutOfBoundsException('Cannot grab last element of an empty list');
        }

        $array = $this->toArray();

        return $array[count($array) - 1];
    }

    /**
     * @template NewValue
     *
     * @param iterable<mixed, NewValue> $values
     *
     * @return self<TValue|NewValue>
     *
     * @psalm-mutation-free
     */
    public function merge(iterable $values): self
    {
        return $this->withOperation(function () use ($values): Generator {
            $iterator = new AppendIterator();

            $iterator->append($this);
            $iterator->append(new self($values));

            yield from $iterator;
        });
    }

    /**
     * Gets the nth element in the list.
     *
     * @throws OutOfBoundsException
     *
     * @return TValue
     */
    public function get(int $key)
    {
        return $this->offsetGet($key);
    }

    public function getAsString(int $key): string
    {
        $value = $this->get($key);
        $tbr = TypeCaster::toString($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'string');
        }

        return $tbr;
    }

    public function getAsInt(int $key): int
    {
        $value = $this->get($key);
        $tbr = TypeCaster::toInt($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'int');
        }

        return $tbr;
    }

    public function getAsFloat(int $key): float
    {
        $value = $this->get($key);
        $tbr = TypeCaster::toFloat($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'float');
        }

        return $tbr;
    }

    public function getAsBool(int $key): bool
    {
        $value = $this->get($key);
        $tbr = TypeCaster::toBool($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'bool');
        }

        return $tbr;
    }

    /**
     * @return null
     */
    public function getAsNull(int $key)
    {
        /** @psalm-suppress UnusedMethodCall */
        $this->get($key);

        return TypeCaster::toNull();
    }

    /**
     * @template U
     *
     * @param class-string<U> $class
     *
     * @return U
     */
    public function getAsObject(int $key, string $class): object
    {
        $value = $this->get($key);
        $tbr = TypeCaster::toClass($value, $class);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, $class);
        }

        return $tbr;
    }

    /**
     * @return CypherMap<mixed>
     */
    public function getAsMap(int $key): CypherMap
    {
        return $this->getAsCypherMap($key);
    }

    /**
     * @return CypherList<mixed>
     */
    public function getAsList(int $key): CypherList
    {
        return $this->getAsCypherList($key);
    }

    /**
     * @template Value
     *
     * @param iterable<mixed, Value> $iterable
     *
     * @return self<Value>
     *
     * @pure
     */
    public static function fromIterable(iterable $iterable): self
    {
        return new self($iterable);
    }

    /**
     * @return CypherMap<mixed>
     */
    public function getAsCypherMap(int $key): CypherMap
    {
        $value = $this->get($key);
        $tbr = TypeCaster::toCypherMap($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, CypherMap::class);
        }

        return $tbr;
    }

    /**
     * @return CypherList<mixed>
     */
    public function getAsCypherList(int $key): CypherList
    {
        $value = $this->get($key);
        $tbr = TypeCaster::toCypherList($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, CypherList::class);
        }

        return $tbr;
    }

    public function getAsDate(int $key): Date
    {
        return $this->getAsObject($key, Date::class);
    }

    public function getAsDateTime(int $key): DateTime
    {
        return $this->getAsObject($key, DateTime::class);
    }

    public function getAsDuration(int $key): Duration
    {
        return $this->getAsObject($key, Duration::class);
    }

    public function getAsLocalDateTime(int $key): LocalDateTime
    {
        return $this->getAsObject($key, LocalDateTime::class);
    }

    public function getAsLocalTime(int $key): LocalTime
    {
        return $this->getAsObject($key, LocalTime::class);
    }

    public function getAsTime(int $key): Time
    {
        return $this->getAsObject($key, Time::class);
    }

    public function getAsNode(int $key): Node
    {
        return $this->getAsObject($key, Node::class);
    }

    public function getAsRelationship(int $key): Relationship
    {
        return $this->getAsObject($key, Relationship::class);
    }

    public function getAsPath(int $key): Path
    {
        return $this->getAsObject($key, Path::class);
    }

    public function getAsCartesian3DPoint(int $key): Cartesian3DPoint
    {
        return $this->getAsObject($key, Cartesian3DPoint::class);
    }

    public function getAsCartesianPoint(int $key): CartesianPoint
    {
        return $this->getAsObject($key, CartesianPoint::class);
    }

    public function getAsWGS84Point(int $key): WGS84Point
    {
        return $this->getAsObject($key, WGS84Point::class);
    }

    public function getAsWGS843DPoint(int $key): WGS843DPoint
    {
        return $this->getAsObject($key, WGS843DPoint::class);
    }

    public function key(): int
    {
        /** @var int */
        return $this->cacheKey();
    }

    /**
     * @return array<int, TValue>
     */
    public function toArray(): array
    {
        $this->preload();

        /** @var array<int, TValue> */
        return $this->cache;
    }

    /**
     * @param callable(TValue, int):bool $callback
     *
     * @return self<TValue>
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
     * @template ReturnType
     *
     * @param callable(TValue, int):ReturnType $callback
     *
     * @return self<ReturnType>
     *
     * @psalm-suppress ImplementedReturnTypeMismatch
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
     * @template TInitial
     *
     * @param TInitial|null                                 $initial
     * @param callable(TInitial|null, TValue, int):TInitial $callback
     *
     * @return TInitial
     */
    final public function reduce(callable $callback, mixed $initial = null): mixed
    {
        foreach ($this as $key => $value) {
            $initial = $callback($initial, $value, $key);
        }

        return $initial;
    }

    /**
     * Iterates over the sequence and applies the callable.
     *
     * @param callable(TValue, int):void $callable
     *
     * @return self<TValue>
     */
    public function each(callable $callable): self
    {
        foreach ($this as $key => $value) {
            $callable($value, $key);
        }

        return $this;
    }
}
