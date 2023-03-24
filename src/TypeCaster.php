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

namespace Laudis\Neo4j;

use function is_a;
use function is_iterable;
use function is_numeric;
use function is_object;
use function is_scalar;

use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;

use function method_exists;

final class TypeCaster
{
    /**
     * @pure
     */
    public static function toString(mixed $value): ?string
    {
        if ($value === null || is_scalar($value) || (is_object($value) && method_exists($value, '__toString'))) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @pure
     */
    public static function toFloat(mixed $value): ?float
    {
        $value = self::toString($value);
        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

    /**
     * @pure
     */
    public static function toInt(mixed $value): ?int
    {
        $value = self::toFloat($value);
        if ($value !== null) {
            return (int) $value;
        }

        return null;
    }

    /**
     * @return null
     *
     * @pure
     */
    public static function toNull()
    {
        return null;
    }

    /**
     * @pure
     */
    public static function toBool(mixed $value): ?bool
    {
        $value = self::toInt($value);
        if ($value !== null) {
            return (bool) $value;
        }

        return null;
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @return T|null
     *
     * @pure
     */
    public static function toClass(mixed $value, string $class): ?object
    {
        if (is_a($value, $class)) {
            /** @var T */
            return $value;
        }

        return null;
    }

    /**
     * @return list<mixed>
     *
     * @psalm-external-mutation-free
     */
    public static function toArray(mixed $value): ?array
    {
        if (is_iterable($value)) {
            $tbr = [];
            /** @var mixed $x */
            foreach ($value as $x) {
                /** @var mixed */
                $tbr[] = $x;
            }

            return $tbr;
        }

        return null;
    }

    /**
     * @return CypherList<mixed>|null
     *
     * @pure
     */
    public static function toCypherList(mixed $value): ?CypherList
    {
        if (is_iterable($value)) {
            return CypherList::fromIterable($value);
        }

        return null;
    }

    /**
     * @return CypherMap<mixed>|null
     */
    public static function toCypherMap(mixed $value): ?CypherMap
    {
        if (is_iterable($value)) {
            return CypherMap::fromIterable($value);
        }

        return null;
    }
}
