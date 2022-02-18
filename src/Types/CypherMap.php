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
     * @param mixed $default
     *
     * @return CypherMap<mixed>
     */
    public function getAsCypherMap(string $key, $default = null): CypherMap
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        $tbr = TypeCaster::toCypherMap($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, __CLASS__);
        }

        return $tbr;
    }

    /**
     * @param mixed $default
     *
     * @return CypherList<mixed>
     */
    public function getAsCypherList(string $key, $default = null): CypherList
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

    /**
     * @param mixed $default
     */
    public function getAsDate(string $key, $default = null): Date
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Date::class);
        }

        return $this->getAsObject($key, Date::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsDateTime(string $key, $default = null): DateTime
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, DateTime::class);
        }

        return $this->getAsObject($key, DateTime::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsDuration(string $key, $default = null): Duration
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Duration::class);
        }

        return $this->getAsObject($key, Duration::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsLocalDateTime(string $key, $default = null): LocalDateTime
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, LocalDateTime::class);
        }

        return $this->getAsObject($key, LocalDateTime::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsLocalTime(string $key, $default = null): LocalTime
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, LocalTime::class);
        }

        return $this->getAsObject($key, LocalTime::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsTime(string $key, $default = null): Time
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Time::class);
        }

        return $this->getAsObject($key, Time::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsNode(string $key, $default = null): Node
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Node::class);
        }

        return $this->getAsObject($key, Node::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsRelationship(string $key, $default = null): Relationship
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Relationship::class);
        }

        return $this->getAsObject($key, Relationship::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsPath(string $key, $default = null): Path
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Path::class);
        }

        return $this->getAsObject($key, Path::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsCartesian3DPoint(string $key, $default = null): Cartesian3DPoint
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Cartesian3DPoint::class);
        }

        return $this->getAsObject($key, Cartesian3DPoint::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsCartesianPoint(string $key, $default = null): CartesianPoint
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, CartesianPoint::class);
        }

        return $this->getAsObject($key, CartesianPoint::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsWGS84Point(string $key, $default = null): WGS84Point
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, WGS84Point::class);
        }

        return $this->getAsObject($key, WGS84Point::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsWGS843DPoint(string $key, $default = null): WGS843DPoint
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
