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
use function array_key_last;
use function array_slice;
use function is_int;
use function is_iterable;
use Laudis\Neo4j\Exception\RuntimeTypeException;
use Laudis\Neo4j\TypeCaster;
use OutOfBoundsException;
use function sort;
use function usort;

/**
 * An immutable ordered sequence of items.
 *
 * @template TValue
 *
 * @extends AbstractCypherSequence<TValue, int>
 *
 * @psalm-immutable
 */
class ArrayList extends AbstractCypherSequence
{
    /**
     * @param iterable<mixed, TValue> $iterable
     */
    public function __construct(iterable $iterable = [])
    {
        if ($iterable instanceof self) {
            /** @psalm-suppress InvalidPropertyAssignmentValue */
            $this->sequence = $iterable->sequence;
        } else {
            $this->sequence = [];
            foreach ($iterable as $value) {
                $this->sequence[] = $value;
            }
        }
    }

    /**
     * @template Value
     *
     * @param iterable<mixed, Value> $iterable
     *
     * @return static<Value>
     */
    protected function withIterable(iterable $iterable): ArrayList
    {
        /** @psalm-suppress UnsafeInstantiation */
        return new static($iterable);
    }

    /**
     * Returns the first element in the sequence.
     *
     * @return TValue
     */
    public function first()
    {
        if (!array_key_exists(0, $this->sequence)) {
            throw new OutOfBoundsException('Cannot grab first element of an empty list');
        }

        return $this->sequence[0];
    }

    /**
     * Returns the last element in the sequence.
     *
     * @return TValue
     */
    public function last()
    {
        $key = array_key_last($this->sequence);
        if (!is_int($key)) {
            throw new OutOfBoundsException('Cannot grab last element of an empty list');
        }

        return $this->sequence[$key];
    }

    /**
     * @param iterable<TValue> $values
     *
     * @return static<TValue>
     */
    public function merge($values): ArrayList
    {
        $tbr = $this->sequence;
        foreach ($values as $value) {
            $tbr[] = $value;
        }

        return $this->withIterable($tbr);
    }

    /**
     * @return static<TValue>
     */
    public function reversed(): ArrayList
    {
        return $this->withIterable(array_reverse($this->sequence));
    }

    /**
     * @return static<TValue>
     */
    public function slice(int $offset, int $length = null): ArrayList
    {
        return $this->withIterable(array_slice($this->sequence, $offset, $length));
    }

    /**
     * @param (callable(TValue, TValue):int)|null $comparator
     *
     * @return static<TValue>
     */
    public function sorted(callable $comparator = null): ArrayList
    {
        $tbr = $this->sequence;
        if ($comparator === null) {
            sort($tbr);
        } else {
            /** @psalm-suppress ImpureFunctionCall */
            usort($tbr, $comparator);
        }

        return $this->withIterable($tbr);
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
        if (!array_key_exists($key, $this->sequence)) {
            throw new OutOfBoundsException(sprintf('Cannot get item in sequence at position: %s', $key));
        }

        return $this->sequence[$key];
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

        return new Map($value);
    }

    /**
     * @return ArrayList<mixed>
     */
    public function getAsArrayList(int $key): ArrayList
    {
        $value = $this->get($key);
        if (!is_iterable($value)) {
            throw new RuntimeTypeException($value, ArrayList::class);
        }

        return new ArrayList($value);
    }

    /**
     * @template Value
     *
     * @param iterable<Value> $iterable
     *
     * @return self<Value>
     *
     * @pure
     */
    public static function fromIterable(iterable $iterable): ArrayList
    {
        return new self($iterable);
    }
}
