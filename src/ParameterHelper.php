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

use Bolt\protocol\IStructure;
use Bolt\protocol\v1\structures\DateTimeZoneId;
use Bolt\protocol\v1\structures\Duration;

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
use Laudis\Neo4j\Enum\ConnectionProtocol;
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
     * @return iterable|scalar|stdClass|IStructure|null
     */
    public static function asParameter(
        mixed $value,
        ConnectionProtocol $protocol
    ): iterable|int|float|bool|string|stdClass|IStructure|null {
        return self::cypherMapToStdClass($value) ??
            self::emptySequenceToArray($value) ??
            self::convertBoltConvertibles($value, $protocol) ??
            self::convertTemporalTypes($value, $protocol) ??
            self::filledIterableToArray($value, $protocol) ??
            self::stringAbleToString($value) ??
            self::filterInvalidType($value);
    }

    /**
     * @pure
     */
    private static function stringAbleToString(mixed $value): ?string
    {
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return null;
    }

    /**
     * @return scalar|null
     *
     * @pure
     */
    private static function filterInvalidType(mixed $value): mixed
    {
        if ($value !== null && !is_scalar($value)) {
            /** @psalm-suppress ImpureFunctionCall */
            throw new InvalidArgumentException(sprintf('Cannot format parameter of type: %s to work with Neo4J', get_debug_type($value)));
        }

        return $value;
    }

    private static function emptySequenceToArray(mixed $value): ?array
    {
        if ((($value instanceof CypherList || $value instanceof CypherMap) && $value->count() === 0) ||
            (is_array($value) && count($value) === 0)) {
            return [];
        }

        return null;
    }

    /**
     * @pure
     *
     * @psalm-suppress ImpureMethodCall
     * @psalm-suppress ImpurePropertyAssignment
     */
    private static function cypherMapToStdClass(mixed $value): ?stdClass
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

    private static function filledIterableToArray(mixed $value, ConnectionProtocol $protocol): ?array
    {
        if (is_iterable($value)) {
            return self::iterableToArray($value, $protocol);
        }

        return null;
    }

    /**
     * @param iterable<mixed> $parameters
     *
     * @return CypherMap<iterable|scalar|stdClass|null>
     */
    public static function formatParameters(iterable $parameters, ConnectionProtocol $connection): CypherMap
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
            $tbr[(string) $key] = self::asParameter($value, $connection);
        }

        return new CypherMap($tbr);
    }

    private static function iterableToArray(iterable $value, ConnectionProtocol $protocol): array
    {
        $tbr = [];
        /**
         * @var mixed $key
         * @var mixed $val
         */
        foreach ($value as $key => $val) {
            if (is_int($key) || is_string($key)) {
                $tbr[$key] = self::asParameter($val, $protocol);
            } else {
                $msg = 'Iterable parameters must have an integer or string as key values, '.gettype($key).' received.';
                throw new InvalidArgumentException($msg);
            }
        }

        return $tbr;
    }

    private static function convertBoltConvertibles(mixed $value, ConnectionProtocol $protocol): ?IStructure
    {
        if ($protocol->isBolt() && $value instanceof BoltConvertibleInterface) {
            return $value->convertToBolt();
        }

        return null;
    }

    private static function convertTemporalTypes(mixed $value, ConnectionProtocol $protocol): ?IStructure
    {
        if ($protocol->isBolt()) {
            if ($value instanceof DateTimeInterface) {
                if ($protocol->compare(ConnectionProtocol::BOLT_V44()) > 0) {
                    return new \Bolt\protocol\v5\structures\DateTimeZoneId(
                        $value->getTimestamp(),
                        ((int) $value->format('u')) * 1000,
                        $value->getTimezone()->getName()
                    );
                }

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
