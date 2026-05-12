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
            'CypherDateTime' => self::decodeCypherDateTime($data),
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
    private static function decodeCypherDateTime(array $data): DateTimeImmutable
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

        return $dt;
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
