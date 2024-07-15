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
use ArrayIterator;
use Generator;

use function is_array;
use function is_callable;
use function is_iterable;

use Laudis\Neo4j\Exception\RuntimeTypeException;
use Laudis\Neo4j\TypeCaster;
use OutOfBoundsException;

/**
 * An immutable ordered sequence of items.
 *
 * @template TValue
 *
 * @extends AbstractCypherSequence<TValue, int>
 */
class ArrayList extends AbstractCypherSequence
{
    /**
     * @param iterable<mixed, TValue>|callable():Generator<mixed, TValue> $iterable
     *
     * @psalm-mutation-free
     */
    public function __construct($iterable = [])
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
     * @param callable():(\Generator<mixed, Value>) $operation
     *
     * @return static<Value>
     *
     * @psalm-mutation-free
     */
    protected function withOperation($operation): AbstractCypherSequence
    {
        /** @psalm-suppress UnsafeInstantiation */
        return new static($operation);
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
     * @psalm-suppress LessSpecificImplementedReturnType
     * @psalm-suppress ImplementedReturnTypeMismatch
     *
     * @return static<TValue|NewValue>
     *
     * @psalm-mutation-free
     */
    public function merge($values): ArrayList
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
            throw new RuntimeTypeException($value, self::class);
        }

        /** @psalm-suppress MixedArgumentTypeCoercion */
        return new ArrayList($value);
    }

    /**
     * @template Value
     *
     * @param iterable<mixed, Value> $iterable
     *
     * @return static<Value>
     *
     * @pure
     */
    public static function fromIterable(iterable $iterable): ArrayList
    {
        /** @psalm-suppress UnsafeInstantiation */
        return new static($iterable);
    }
}
