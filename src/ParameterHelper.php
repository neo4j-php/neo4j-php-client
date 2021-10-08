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

namespace Laudis\Neo4j;

use function count;
use function gettype;
use InvalidArgumentException;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use stdClass;

/**
 * Parameter helper class providing convenient functions for converting php objects to cypher parameters.
 *
 * @psalm-immutable
 */
final class ParameterHelper
{
    /**
     * @template T
     *
     * @param iterable<array-key, T> $iterable
     *
     * @return CypherList<T>
     *
     * @pure
     */
    public static function asList(iterable $iterable): CypherList
    {
        return new CypherList($iterable);
    }

    /**
     * @template T
     *
     * @param iterable<array-key, T> $iterable
     *
     * @return CypherMap<T>
     */
    public static function asMap(iterable $iterable): CypherMap
    {
        $tbr = [];
        foreach ($iterable as $key => $value) {
            $tbr[(string) $key] = $value;
        }

        return new CypherMap($tbr);
    }

    /**
     * @param mixed $value
     *
     * @return iterable|scalar|stdClass|null
     */
    public static function asParameter($value)
    {
        return self::emptyDictionaryToStdClass($value) ??
            self::emptySequenceToArray($value) ??
            self::filledIterableToArray($value) ??
            self::stringAbleToString($value) ??
            self::filterInvalidType($value);
    }

    /**
     * @param mixed $value
     *
     * @pure
     */
    private static function stringAbleToString($value): ?string
    {
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @param mixed $value
     *
     * @return scalar|null
     *
     * @pure
     */
    private static function filterInvalidType($value)
    {
        if ($value !== null && !is_scalar($value)) {
            throw new InvalidArgumentException('Parameters must be iterable, scalar, null or stringable');
        }

        return $value;
    }

    /**
     * @param mixed $value
     *
     * @pure
     */
    private static function emptySequenceToArray($value): ?array
    {
        if ((($value instanceof CypherList || $value instanceof CypherMap) && $value->count() === 0) ||
            (is_array($value) && count($value) === 0)) {
            return [];
        }

        return null;
    }

    /**
     * @param mixed $value
     *
     * @pure
     */
    private static function emptyDictionaryToStdClass($value): ?stdClass
    {
        if (($value instanceof CypherMap) && $value->count() === 0) {
            return new stdClass();
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private static function filledIterableToArray($value): ?array
    {
        if (is_iterable($value)) {
            return self::iterableToArray($value);
        }

        return null;
    }

    /**
     * @param iterable<iterable|scalar|null> $parameters
     *
     * @return CypherMap<iterable|scalar|stdClass|null>
     */
    public static function formatParameters(iterable $parameters): CypherMap
    {
        /** @var array<string, iterable|scalar|stdClass|null> $tbr */
        $tbr = [];
        foreach ($parameters as $key => $value) {
            if (!(is_int($key) || is_string($key))) {
                $msg = 'The parameters must have an integer or string as key values, '.gettype($key).' received.';
                throw new InvalidArgumentException($msg);
            }
            $tbr[(string) $key] = self::asParameter($value);
        }

        return new CypherMap($tbr);
    }

    private static function iterableToArray(iterable $value): array
    {
        $tbr = [];
        /**
         * @var mixed $key
         * @var mixed $val
         */
        foreach ($value as $key => $val) {
            if (is_int($key) || is_string($key)) {
                $tbr[$key] = self::asParameter($val);
            } else {
                $msg = 'Iterable parameters must have an integer or string as key values, '.gettype($key).' received.';
                throw new InvalidArgumentException($msg);
            }
        }

        return $tbr;
    }
}
