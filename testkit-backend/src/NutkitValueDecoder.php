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

namespace Laudis\Neo4j\TestkitBackend;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\DateTimeZoneId;
use Laudis\Neo4j\Types\Duration;
use Laudis\Neo4j\Types\LocalDateTime;
use Laudis\Neo4j\Types\LocalTime;
use Laudis\Neo4j\Types\Time;

/**
 * Decodes nutkit JSON parameter payloads ({@see \nutkit\backend\Encoder}) into PHP values for the driver.
 */
final class NutkitValueDecoder
{
    /**
     * @param array{name?: string, data?: array<string, mixed>} $param
     */
    public static function decode(array $param): mixed
    {
        $name = $param['name'] ?? '';
        $data = $param['data'] ?? [];

        return match ($name) {
            'CypherNull' => null,
            'CypherDate' => self::decodeCypherDate($data),
            'CypherTime' => self::decodeCypherTime($data),
            'CypherDateTime' => self::decodeCypherDateTime($data),
            'CypherDuration' => self::decodeCypherDuration($data),
            'CypherMap' => self::decodeCypherMap($data),
            'CypherList' => self::decodeCypherList($data),
            default => self::decodeScalarWrapper($name, $data),
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function decodeScalarWrapper(string $name, array $data): mixed
    {
        if (!array_key_exists('value', $data)) {
            throw new InvalidArgumentException('Unsupported nutkit type or missing data.value for: '.$name);
        }

        return $data['value'];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function decodeCypherMap(array $data): CypherMap
    {
        $raw = $data['value'] ?? [];
        if (!is_iterable($raw)) {
            throw new InvalidArgumentException('CypherMap.value must be iterable');
        }
        $map = [];
        foreach ($raw as $k => $v) {
            $map[(string) $k] = is_array($v) && isset($v['name'], $v['data'])
                ? self::decode($v)
                : $v;
        }

        return new CypherMap($map);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function decodeCypherList(array $data): CypherList
    {
        $raw = $data['value'] ?? [];
        if (!is_iterable($raw)) {
            throw new InvalidArgumentException('CypherList.value must be iterable');
        }
        $list = [];
        foreach ($raw as $v) {
            $list[] = is_array($v) && isset($v['name'], $v['data'])
                ? self::decode($v)
                : $v;
        }

        return new CypherList($list);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function decodeCypherDate(array $data): Date
    {
        $y = (int) ($data['year'] ?? 0);
        $m = (int) ($data['month'] ?? 0);
        $d = (int) ($data['day'] ?? 0);

        $dt = DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            sprintf('%04d-%02d-%02d', $y, $m, $d),
            new DateTimeZone('UTC')
        );
        if ($dt === false) {
            throw new InvalidArgumentException('Invalid CypherDate');
        }

        return new Date(intdiv($dt->getTimestamp(), 86400));
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function decodeCypherTime(array $data): Time|LocalTime
    {
        $nanosecondsSinceMidnight = self::composeNanosecondsSinceMidnight($data);
        $utcOffsetS = array_key_exists('utc_offset_s', $data) && $data['utc_offset_s'] !== null
            ? (int) $data['utc_offset_s']
            : null;

        if ($utcOffsetS === null) {
            return new LocalTime($nanosecondsSinceMidnight);
        }

        return new Time($nanosecondsSinceMidnight, $utcOffsetS);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function decodeCypherDateTime(array $data): LocalDateTime|DateTime|DateTimeZoneId
    {
        $y = (int) ($data['year'] ?? 0);
        $m = (int) ($data['month'] ?? 0);
        $d = (int) ($data['day'] ?? 0);
        $h = (int) ($data['hour'] ?? 0);
        $i = (int) ($data['minute'] ?? 0);
        $s = (int) ($data['second'] ?? 0);
        $ns = (int) ($data['nanosecond'] ?? 0);
        $utcOffsetS = array_key_exists('utc_offset_s', $data) && $data['utc_offset_s'] !== null
            ? (int) $data['utc_offset_s']
            : null;
        $timezoneId = $data['timezone_id'] ?? null;
        if ($timezoneId === '') {
            $timezoneId = null;
        }

        $micro = intdiv($ns, 1000);
        $base = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $y, $m, $d, $h, $i, $s);

        if (is_string($timezoneId)) {
            $tz = new DateTimeZone($timezoneId);
            $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $base, $tz);
            if ($dt === false) {
                throw new InvalidArgumentException('Invalid CypherDateTime (timezone_id)');
            }
            if ($utcOffsetS !== null && $dt->getOffset() !== $utcOffsetS) {
                throw new InvalidArgumentException('CypherDateTime offset does not match timezone_id');
            }
        } elseif ($utcOffsetS !== null) {
            $tz = self::timezoneFromOffsetSeconds($utcOffsetS);
            $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $base, $tz);
            if ($dt === false) {
                throw new InvalidArgumentException('Invalid CypherDateTime (offset)');
            }
        } else {
            $dt = new DateTimeImmutable($base, new DateTimeZone('UTC'));
        }

        if ($micro > 0) {
            $dt = $dt->modify(sprintf('+%d microseconds', $micro));
        }

        if (is_string($timezoneId)) {
            return new DateTimeZoneId($dt->getTimestamp(), $ns, $timezoneId);
        }

        if ($utcOffsetS !== null) {
            return new DateTime($dt->getTimestamp(), $ns, $utcOffsetS, false);
        }

        return new LocalDateTime($dt->getTimestamp(), $ns);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function decodeCypherDuration(array $data): Duration
    {
        return new Duration(
            (int) ($data['months'] ?? 0),
            (int) ($data['days'] ?? 0),
            (int) ($data['seconds'] ?? 0),
            (int) ($data['nanoseconds'] ?? 0),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function composeNanosecondsSinceMidnight(array $data): int
    {
        $hour = (int) ($data['hour'] ?? 0);
        $minute = (int) ($data['minute'] ?? 0);
        $second = (int) ($data['second'] ?? 0);
        $nanosecond = (int) ($data['nanosecond'] ?? 0);

        return $hour * 3_600_000_000_000
            + $minute * 60_000_000_000
            + $second * 1_000_000_000
            + $nanosecond;
    }

    private static function timezoneFromOffsetSeconds(int $offsetSec): DateTimeZone
    {
        $sign = $offsetSec >= 0 ? '+' : '-';
        $abs = abs($offsetSec);
        $hours = intdiv($abs, 3600);
        $mins = intdiv($abs % 3600, 60);

        return new DateTimeZone(sprintf('%s%02d:%02d', $sign, $hours, $mins));
    }
}
