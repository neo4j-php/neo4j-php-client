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
 *
 * @psalm-immutable
 */
final class CypherMap extends Map
{
    /**
     * @param mixed $default
     *
     * @return CypherMap<mixed>
     */
    public function getAsCypherMap(int $key, $default = null): CypherMap
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            $value = $this->get($key, $default);
        }
        $tbr = TypeCaster::toCypherMap($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, CypherMap::class);
        }

        return $tbr;
    }

    /**
     * @param mixed $default
     *
     * @return CypherList<mixed>
     */
    public function getAsCypherList(int $key, $default = null): CypherList
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
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
    public function getAsDate(int $key, $default = null): Date
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Date::class);
        }

        return $this->getAsObject($key, Date::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsDateTime(int $key, $default = null): DateTime
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, DateTime::class);
        }

        return $this->getAsObject($key, DateTime::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsDuration(int $key, $default = null): Duration
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Duration::class);
        }

        return $this->getAsObject($key, Duration::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsLocalDateTime(int $key, $default = null): LocalDateTime
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, LocalDateTime::class);
        }

        return $this->getAsObject($key, LocalDateTime::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsLocalTime(int $key, $default = null): LocalTime
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, LocalTime::class);
        }

        return $this->getAsObject($key, LocalTime::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsTime(int $key, $default = null): Time
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Time::class);
        }

        return $this->getAsObject($key, Time::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsNode(int $key, $default = null): Node
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Node::class);
        }

        return $this->getAsObject($key, Node::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsRelationship(int $key, $default = null): Relationship
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Relationship::class);
        }

        return $this->getAsObject($key, Relationship::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsPath(int $key, $default = null): Path
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Path::class);
        }

        return $this->getAsObject($key, Path::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsCartesian3DPoint(int $key, $default = null): Cartesian3DPoint
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Cartesian3DPoint::class);
        }

        return $this->getAsObject($key, Cartesian3DPoint::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsCartesianPoint(int $key, $default = null): CartesianPoint
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, CartesianPoint::class);
        }

        return $this->getAsObject($key, CartesianPoint::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsWGS84Point(int $key, $default = null): WGS84Point
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, WGS84Point::class);
        }

        return $this->getAsObject($key, WGS84Point::class, $default);
    }

    /**
     * @param mixed $default
     */
    public function getAsWGS843DPoint(int $key, $default = null): WGS843DPoint
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, WGS843DPoint::class);
        }

        return $this->getAsObject($key, WGS843DPoint::class, $default);
    }
}
