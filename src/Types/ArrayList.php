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

use AppendIterator;
use ArrayIterator;
use function is_array;
use function is_iterable;
use Laudis\Neo4j\Exception\RuntimeTypeException;
use Laudis\Neo4j\TypeCaster;
use OutOfBoundsException;

/**
 * An immutable ordered sequence of items.
 *
 * @template TValue
 * @template OriginalValue
 * @template OriginalKey of array-key
 *
 * @extends AbstractCypherSequence<TValue, int, OriginalValue, OriginalKey>
 */
class ArrayList extends AbstractCypherSequence
{
    /**
     * @param iterable<OriginalKey, OriginalValue> $iterable
     * @param callable(iterable<OriginalKey, OriginalValue>):(\Generator<int, TValue>) $transformation
     *
     * @psalm-return (func_num_args() is 1 ? self<OriginalValue, OriginalValue, int> : self<TValue, OriginalValue, OriginalKey>)
     *
     * @psalm-suppress InvalidPropertyAssignmentValue
     * @psalm-suppress MissingClosureReturnType
     */
    public function __construct(iterable $iterable = [], $transformation = null)
    {
        $this->sequence = $iterable;
        $this->typeTransformation = static function () use ($iterable) {
            yield from $iterable;
        };
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
     * @param iterable<NewValue> $values
     *
     * @return static<TValue&NewValue, TValue&NewValue, int>
     */
    public function merge($values): ArrayList
    {
        return new self($this, static function () use ($values) {
            $iterator = new AppendIterator();
            $iterator->append($this);
            $iterator->append(is_array($values) ? new ArrayIterator($values) : $values);

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
     * @return Map<mixed>
     */
    public function getAsMap(int $key): Map
    {
        $value = $this->get($key);
        if (!is_iterable($value)) {
            throw new RuntimeTypeException($value, Map::class);
        }

        /** @psalm-suppress MixedArgumentTypeCoercion */
        return new Map($value);
    }

    /**
     * @return ArrayList<mixed>
     */
    public function getAsArrayList(int $key): ArrayList
    {
        $value = $this->get($key);
        if (!is_iterable($value)) {
            throw new RuntimeTypeException($value, __CLASS__);
        }

        /** @psalm-suppress MixedArgumentTypeCoercion */
        return new ArrayList($value);
    }

    /**
     * @template Value
     *
     * @param iterable<array-key, Value> $iterable
     *
     * @return self<Value, Value, array-key>
     */
    public static function fromIterable(iterable $iterable): ArrayList
    {
        return new self($iterable);
    }

    protected function withOperation($operation): ArrayList
    {
        return new self($this, $operation);
    }
}
