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
use Bolt\protocol\v1\structures\DateTime as BoltV1DateTime;
use Bolt\protocol\v1\structures\DateTimeZoneId;
use Bolt\protocol\v1\structures\Duration;
use Bolt\protocol\v5\structures\DateTime as BoltV5DateTime;
use Bolt\protocol\v5\structures\DateTimeZoneId as BoltV5DateTimeZoneId;

use function count;

use DateInterval;
use DateTimeImmutable;
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
        ConnectionProtocol $protocol,
        bool $boltUtcPatchNegotiated = false,
    ): iterable|int|float|bool|string|stdClass|IStructure|null {
        return self::passThroughBoltStructure($value) ??
            self::cypherMapToStdClass($value) ??
            self::emptySequenceToArray($value) ??
            self::ogmDateTimeZoneIdToBolt($value, $protocol, $boltUtcPatchNegotiated) ??
            self::convertBoltConvertibles($value) ??
            self::convertTemporalTypes($value, $protocol, $boltUtcPatchNegotiated) ??
            self::filledIterableToArray($value, $protocol, $boltUtcPatchNegotiated) ??
            self::stringAbleToString($value) ??
            self::filterInvalidType($value);
    }

    private static function passThroughBoltStructure(mixed $value): ?IStructure
    {
        if ($value instanceof IStructure) {
            return $value;
        }

        return null;
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
        if ((($value instanceof CypherList || $value instanceof CypherMap) && $value->count() === 0)
            || (is_array($value) && count($value) === 0)) {
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

    private static function filledIterableToArray(mixed $value, ConnectionProtocol $protocol, bool $boltUtcPatchNegotiated = false): ?array
    {
        if (is_iterable($value)) {
            return self::iterableToArray($value, $protocol, $boltUtcPatchNegotiated);
        }

        return null;
    }

    /**
     * When the server negotiated {@code patch_bolt: ["utc"]} on Bolt 4.3/4.4, temporal values use the same PackStream
     * structures as Bolt 5+ (Tv2 / 0x49) instead of legacy T / 0x46.
     */
    private static function useBoltV5TemporalOnWire(ConnectionProtocol $protocol, bool $boltUtcPatchNegotiated): bool
    {
        if ($protocol->compare(ConnectionProtocol::BOLT_V44()) > 0) {
            return true;
        }

        return $boltUtcPatchNegotiated
            && $protocol->compare(ConnectionProtocol::BOLT_V43()) >= 0
            && $protocol->compare(ConnectionProtocol::BOLT_V5()) < 0;
    }

    /**
     * @param iterable<array-key, mixed> $parameters
     *
     * @return CypherMap<iterable|scalar|stdClass|null>
     */
    public static function formatParameters(iterable $parameters, ConnectionProtocol $connection, bool $boltUtcPatchNegotiated = false): CypherMap
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
            $tbr[(string) $key] = self::asParameter($value, $connection, $boltUtcPatchNegotiated);
        }

        return new CypherMap($tbr);
    }

    private static function iterableToArray(iterable $value, ConnectionProtocol $protocol, bool $boltUtcPatchNegotiated = false): array
    {
        $tbr = [];
        /**
         * @var mixed $key
         * @var mixed $val
         */
        foreach ($value as $key => $val) {
            if (is_int($key) || is_string($key)) {
                $tbr[$key] = self::asParameter($val, $protocol, $boltUtcPatchNegotiated);
            } else {
                $msg = 'Iterable parameters must have an integer or string as key values, '.gettype(
                    $key
                ).' received.';
                throw new InvalidArgumentException($msg);
            }
        }

        return $tbr;
    }

    private static function convertBoltConvertibles(mixed $value): ?IStructure
    {
        if ($value instanceof BoltConvertibleInterface) {
            return $value->convertToBolt();
        }

        return null;
    }

    /**
     * {@see Types\DateTimeZoneId} stores Bolt ≤4.4 "local epoch" seconds; Bolt 5+ wire uses UTC epoch.
     */
    private static function ogmDateTimeZoneIdToBolt(mixed $value, ConnectionProtocol $protocol, bool $boltUtcPatchNegotiated = false): ?IStructure
    {
        if (!$value instanceof Types\DateTimeZoneId) {
            return null;
        }

        $instant = $value->toDateTime();
        $nanos = $value->getNanoseconds();
        $tzName = $value->getTimezoneIdentifier();

        return self::useBoltV5TemporalOnWire($protocol, $boltUtcPatchNegotiated)
            ? new BoltV5DateTimeZoneId($instant->getTimestamp(), $nanos, $tzName)
            : new DateTimeZoneId($value->getSeconds(), $nanos, $tzName);
    }

    private static function convertTemporalTypes(mixed $value, ConnectionProtocol $protocol, bool $boltUtcPatchNegotiated = false): ?IStructure
    {
        if ($value instanceof DateTimeInterface) {
            $immutable = $value instanceof DateTimeImmutable
                ? $value
                : DateTimeImmutable::createFromMutable($value);
            $nanos = ((int) $immutable->format('u')) * 1000;
            $offsetSec = $immutable->getOffset();
            $tzName = $immutable->getTimezone()->getName();

            if (self::isNamedIanaStyleTimezoneId($tzName)) {
                // Bolt ≤4.4: civil seconds (TestKit simple_jolt). Bolt 5+: UTC Unix epoch on the wire.
                // With patch_bolt utc on 4.3/4.4, use the same v5 wire encoding as Neo4j 5+ (Tv2).
                return self::useBoltV5TemporalOnWire($protocol, $boltUtcPatchNegotiated)
                    ? new BoltV5DateTimeZoneId($immutable->getTimestamp(), $nanos, $tzName)
                    : new DateTimeZoneId(
                        Types\DateTimeZoneId::encodeBoltCivilSecondsForInstant($immutable, $immutable->getTimezone()),
                        $nanos,
                        $tzName
                    );
            }

            return self::useBoltV5TemporalOnWire($protocol, $boltUtcPatchNegotiated)
                ? new BoltV5DateTime($immutable->getTimestamp(), $nanos, $offsetSec)
                : new BoltV1DateTime($immutable->getTimestamp() + $offsetSec, $nanos, $offsetSec);
        }

        if ($value instanceof DateInterval) {
            return new Duration(
                $value->y * 12 + $value->m,
                $value->d,
                $value->h * 60 * 60 * $value->i * 60 + $value->s * 60,
                (int) ($value->f * 1000)
            );
        }

        return null;
    }

    private static function isNamedIanaStyleTimezoneId(string $name): bool
    {
        if ($name === '' || $name === 'UTC' || $name === 'GMT' || strtoupper($name) === 'Z') {
            return false;
        }

        return preg_match('/^[+-](?:\d{2}:\d{2}|\d{4})$/', $name) !== 1;
    }
}
