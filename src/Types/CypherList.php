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
 * @extends AbstractCypherSequence<int, TValue>
 *
 * @psalm-immutable
 */
final class CypherList extends AbstractCypherSequence
{
    /**
     * @param iterable<TValue> $iterable
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
     * @return CypherList<TValue>
     */
    public function merge($values): CypherList
    {
        $tbr = $this->sequence;
        foreach ($values as $value) {
            $tbr[] = $value;
        }

        return new CypherList($tbr);
    }

    /**
     * @return CypherList<TValue>
     */
    public function reversed(): CypherList
    {
        return new CypherList(array_reverse($this->sequence));
    }

    /**
     * @return CypherList<TValue>
     */
    public function slice(int $offset, int $length = null): CypherList
    {
        return new CypherList(array_slice($this->sequence, $offset, $length));
    }

    /**
     * @param (pure-callable(TValue, TValue):int)|null $comparator
     *
     * @return CypherList<TValue>
     */
    public function sorted(callable $comparator = null): CypherList
    {
        $tbr = $this->sequence;
        if ($comparator === null) {
            sort($tbr);
        } else {
            usort($tbr, $comparator);
        }

        return new CypherList($tbr);
    }

    /**
     * @pure
     */
    public static function fromIterable(iterable $iterable): AbstractCypherSequence
    {
        return new self($iterable);
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
}
