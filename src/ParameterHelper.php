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

use Bolt\structures\DateTimeZoneId;
use Bolt\structures\Duration;
use Bolt\structures\IStructure;
use function count;
use DateInterval;
use DateTimeInterface;
use function get_debug_type;
use function gettype;
use InvalidArgumentException;
use function is_array;
use function is_int;
use function is_object;
use function is_string;
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;
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
     * @return iterable|scalar|stdClass|IStructure|null
     */
    public static function asParameter($value, bool $boltDriver = false)
    {
        return self::cypherMapToStdClass($value) ??
            self::emptySequenceToArray($value) ??
            self::convertBoltConvertibles($value, $boltDriver) ??
            self::convertTemporalTypes($value, $boltDriver) ??
            self::filledIterableToArray($value, $boltDriver) ??
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
            /** @psalm-suppress ImpureFunctionCall */
            throw new InvalidArgumentException(sprintf('Cannot format parameter of type: %s to work with Neo4J', get_debug_type($value)));
        }

        return $value;
    }

    /**
     * @param mixed $value
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
     *
     * @psalm-suppress ImpureMethodCall
     * @psalm-suppress ImpurePropertyAssignment
     */
    private static function cypherMapToStdClass($value): ?stdClass
    {
        if ($value instanceof CypherMap) {
            $tbr = new stdClass();
            foreach ($value as $key => $val) {
                $tbr->$key = $val;
            }

            return $tbr;
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private static function filledIterableToArray($value, bool $boltDriver): ?array
    {
        if (is_iterable($value)) {
            return self::iterableToArray($value, $boltDriver);
        }

        return null;
    }

    /**
     * @param iterable<mixed> $parameters
     *
     * @return CypherMap<iterable|scalar|stdClass|null>
     */
    public static function formatParameters(iterable $parameters, bool $boltDriver = false): CypherMap
    {
        /** @var array<string, iterable|scalar|stdClass|null> $tbr */
        $tbr = [];
        /**
         * @var mixed $key
         * @var mixed $value
         */
        foreach ($parameters as $key => $value) {
            if (!(is_int($key) || is_string($key))) {
                $msg = 'The parameters must have an integer or string as key values, '.gettype($key).' received.';
                throw new InvalidArgumentException($msg);
            }
            $tbr[(string) $key] = self::asParameter($value, $boltDriver);
        }

        return new CypherMap($tbr);
    }

    private static function iterableToArray(iterable $value, bool $boltDriver): array
    {
        $tbr = [];
        /**
         * @var mixed $key
         * @var mixed $val
         */
        foreach ($value as $key => $val) {
            if (is_int($key) || is_string($key)) {
                $tbr[$key] = self::asParameter($val, $boltDriver);
            } else {
                $msg = 'Iterable parameters must have an integer or string as key values, '.gettype($key).' received.';
                throw new InvalidArgumentException($msg);
            }
        }

        return $tbr;
    }

    /**
     * @param mixed $value
     */
    private static function convertBoltConvertibles($value, bool $boltDriver): ?IStructure
    {
        if ($boltDriver && $value instanceof BoltConvertibleInterface) {
            return $value->convertToBolt();
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private static function convertTemporalTypes($value, bool $boltDriver): ?IStructure
    {
        if ($boltDriver) {
            if ($value instanceof DateTimeInterface) {
                return new DateTimeZoneId(
                    $value->getTimestamp(),
                    ((int) $value->format('u')) * 1000,
                    $value->getTimezone()->getName()
                );
            }

            if ($value instanceof DateInterval) {
                return new Duration(
                    $value->y * 12 + $value->m,
                    $value->d,
                    $value->h * 60 * 60 * $value->i * 60 + $value->s * 60,
                    (int) ($value->f * 1000)
                );
            }
        }

        return null;
    }
}
