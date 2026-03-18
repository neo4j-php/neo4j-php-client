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

use Laudis\Neo4j\Exception\InvalidTypeCast;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Stringable;

final class TypeCaster
{
    /**
     * @throws InvalidTypeCast
     *
     * @pure
     */
    public static function toString(mixed $value): string
    {
        if ($value === null || is_scalar($value) || $value instanceof Stringable) {
            return (string) $value;
        }

        throw new InvalidTypeCast($value, 'string');
    }

    /**
     * @throws InvalidTypeCast
     *
     * @pure
     */
    public static function toFloat(mixed $value): float
    {
        if (is_numeric($value) || is_bool($value)) {
            return (float) $value;
        }

        try {
            $stringValue = self::toString($value);
        } catch (InvalidTypeCast) {
            throw new InvalidTypeCast($value, 'float');
        }

        if (is_numeric($stringValue)) {
            return (float) $stringValue;
        }

        throw new InvalidTypeCast($value, 'float');
    }

    /**
     * @throws InvalidTypeCast
     *
     * @pure
     */
    public static function toInt(mixed $value): int
    {
        if (is_numeric($value) || is_bool($value)) {
            return (int) $value;
        }

        try {
            $stringValue = self::toString($value);
        } catch (InvalidTypeCast) {
            throw new InvalidTypeCast($value, 'int');
        }

        if (is_numeric($stringValue)) {
            return (int) $stringValue;
        }

        throw new InvalidTypeCast($value, 'int');
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
     * @throws InvalidTypeCast
     *
     * @pure
     */
    public static function toBool(mixed $value): bool
    {
        if (is_bool($value) || is_numeric($value)) {
            return (bool) $value;
        }

        try {
            $stringValue = self::toString($value);
        } catch (InvalidTypeCast) {
            throw new InvalidTypeCast($value, 'bool');
        }

        if (is_numeric($stringValue)) {
            return (bool) $stringValue;
        }

        throw new InvalidTypeCast($value, 'bool');
    }

    /**
     * @template T
     *
     * @param class-string<T> $class
     *
     * @throws InvalidTypeCast
     *
     * @return T
     *
     * @pure
     */
    public static function toClass(mixed $value, string $class): object
    {
        if (is_a($value, $class)) {
            /** @var T */
            return $value;
        }

        throw new InvalidTypeCast($value, $class);
    }

    /**
     * @throws InvalidTypeCast
     *
     * @return list<mixed>
     *
     * @psalm-external-mutation-free
     */
    public static function toArray(mixed $value): array
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

        throw new InvalidTypeCast($value, 'array');
    }

    /**
     * @throws InvalidTypeCast
     *
     * @return CypherList<mixed>
     *
     * @pure
     */
    public static function toCypherList(mixed $value): CypherList
    {
        if (is_iterable($value)) {
            return CypherList::fromIterable($value);
        }

        throw new InvalidTypeCast($value, CypherList::class);
    }

    /**
     * @throws InvalidTypeCast
     *
     * @return CypherMap<mixed>
     */
    public static function toCypherMap(mixed $value): CypherMap
    {
        if (is_iterable($value)) {
            return CypherMap::fromIterable($value);
        }

        throw new InvalidTypeCast($value, CypherMap::class);
    }
}
