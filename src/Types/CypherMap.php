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

use function func_num_args;

use Laudis\Neo4j\Exception\RuntimeTypeException;
use Laudis\Neo4j\TypeCaster;

/**
 * An immutable ordered map of items.
 *
 * @template TValue
 *
 * @extends Map<TValue>
 */
final class CypherMap extends Map
{
    /**
     * @return CypherMap<mixed>
     */
    public function getAsCypherMap(string $key, mixed $default = null): CypherMap
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        $tbr = TypeCaster::toCypherMap($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, self::class);
        }

        return $tbr;
    }

    /**
     * @return CypherList<mixed>
     */
    public function getAsCypherList(string $key, mixed $default = null): CypherList
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        $tbr = TypeCaster::toCypherList($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, CypherList::class);
        }

        return $tbr;
    }

    public function getAsDate(string $key, mixed $default = null): Date
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Date::class);
        }

        return $this->getAsObject($key, Date::class, $default);
    }

    public function getAsDateTime(string $key, mixed $default = null): DateTime
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, DateTime::class);
        }

        return $this->getAsObject($key, DateTime::class, $default);
    }

    public function getAsDuration(string $key, mixed $default = null): Duration
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Duration::class);
        }

        return $this->getAsObject($key, Duration::class, $default);
    }

    public function getAsLocalDateTime(string $key, mixed $default = null): LocalDateTime
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, LocalDateTime::class);
        }

        return $this->getAsObject($key, LocalDateTime::class, $default);
    }

    public function getAsLocalTime(string $key, mixed $default = null): LocalTime
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, LocalTime::class);
        }

        return $this->getAsObject($key, LocalTime::class, $default);
    }

    public function getAsTime(string $key, mixed $default = null): Time
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Time::class);
        }

        return $this->getAsObject($key, Time::class, $default);
    }

    public function getAsNode(string $key, mixed $default = null): Node
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Node::class);
        }

        return $this->getAsObject($key, Node::class, $default);
    }

    public function getAsRelationship(string $key, mixed $default = null): Relationship
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Relationship::class);
        }

        return $this->getAsObject($key, Relationship::class, $default);
    }

    public function getAsPath(string $key, mixed $default = null): Path
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Path::class);
        }

        return $this->getAsObject($key, Path::class, $default);
    }

    public function getAsCartesian3DPoint(string $key, mixed $default = null): Cartesian3DPoint
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Cartesian3DPoint::class);
        }

        return $this->getAsObject($key, Cartesian3DPoint::class, $default);
    }

    public function getAsCartesianPoint(string $key, mixed $default = null): CartesianPoint
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, CartesianPoint::class);
        }

        return $this->getAsObject($key, CartesianPoint::class, $default);
    }

    public function getAsWGS84Point(string $key, mixed $default = null): WGS84Point
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, WGS84Point::class);
        }

        return $this->getAsObject($key, WGS84Point::class, $default);
    }

    public function getAsWGS843DPoint(string $key, mixed $default = null): WGS843DPoint
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, WGS843DPoint::class);
        }

        return $this->getAsObject($key, WGS843DPoint::class, $default);
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
    public static function fromIterable(iterable $iterable): CypherMap
    {
        return new self($iterable);
    }

    /**
     * @psalm-mutation-free
     */
    public function pluck(string $key): CypherList
    {
        return CypherList::fromIterable(parent::pluck($key));
    }

    /**
     * @psalm-mutation-free
     */
    public function keyBy(string $key): CypherMap
    {
        return CypherMap::fromIterable(parent::keyBy($key));
    }
}
