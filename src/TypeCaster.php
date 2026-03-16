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
use function is_scalar;

use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Stringable;

final class TypeCaster
{
    /**
     * @pure
     */
    public static function toString(mixed $value): ?string
    {
        if ($value === null || is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @pure
     */
    public static function toFloat(mixed $value): ?float
    {
        if (is_numeric($value) || is_bool($value)) {
            return (float) $value;
        }

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
        if (is_numeric($value) || is_bool($value)) {
            return (int) $value;
        }

        $value = self::toString($value);

        if (is_numeric($value)) {
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
        if (is_bool($value) || is_numeric($value)) {
            return (bool) $value;
        }

        $value = self::toString($value);

        if (is_numeric($value)) {
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
                /** @psalm-suppress MixedAssignment */
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
