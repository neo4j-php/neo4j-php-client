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

namespace Laudis\Neo4j\TestkitBackend\Responses\Types;

use DateTimeInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\Types\DateTime as Neo4jDateTime;
use Laudis\Neo4j\Types\DateTimeZoneId as Neo4jDateTimeZoneId;
use Laudis\Neo4j\Types\Duration;
use Laudis\Neo4j\Types\LocalDateTime;
use Laudis\Neo4j\Types\LocalTime;
use Laudis\Neo4j\Types\Time;

/**
 * Nutkit {@see \nutkit\backend\Encoder} expects several Cypher types with flat {@code data} fields
 * (not wrapped in {@code data.value}); this response mirrors that shape for JSON serialization.
 */
final class NutkitFlatCypherValue implements TestkitResponseInterface
{
    /**
     * @param array<string, int|string|null> $data
     */
    public function __construct(
        private string $name,
        private array $data,
    ) {
    }

    public static function cypherDateFromNeo4jDate(Date $date): self
    {
        $dt = $date->toDateTime();

        return new self('CypherDate', [
            'year' => (int) $dt->format('Y'),
            'month' => (int) $dt->format('n'),
            'day' => (int) $dt->format('j'),
        ]);
    }

    public static function cypherTimeFromNeo4jTime(Time $time): self
    {
        $parts = self::decomposeNanosecondsSinceMidnight($time->getNanoSeconds());

        return new self('CypherTime', [
            'hour' => $parts['hour'],
            'minute' => $parts['minute'],
            'second' => $parts['second'],
            'nanosecond' => $parts['nanosecond'],
            'utc_offset_s' => $time->getTzOffsetSeconds(),
        ]);
    }

    public static function cypherTimeFromNeo4jLocalTime(LocalTime $time): self
    {
        $parts = self::decomposeNanosecondsSinceMidnight($time->getNanoseconds());

        return new self('CypherTime', [
            'hour' => $parts['hour'],
            'minute' => $parts['minute'],
            'second' => $parts['second'],
            'nanosecond' => $parts['nanosecond'],
        ]);
    }

    public static function cypherDateTimeFromDateTimeInterface(DateTimeInterface $dt, bool $includeOffset = true): self
    {
        return new self('CypherDateTime', self::dateTimeToNutkitFields($dt, $includeOffset));
    }

    public static function cypherDateTimeFromNeo4jDateTime(Neo4jDateTime $dt): self
    {
        return self::cypherDateTimeFromDateTimeInterface($dt->toDateTime());
    }

    public static function cypherDateTimeFromNeo4jDateTimeZoneId(Neo4jDateTimeZoneId $dt): self
    {
        return self::cypherDateTimeFromDateTimeInterface($dt->toDateTime());
    }

    public static function cypherDateTimeFromNeo4jLocalDateTime(LocalDateTime $dt): self
    {
        return self::cypherDateTimeFromDateTimeInterface($dt->toDateTime(), false);
    }

    public static function cypherDurationFromNeo4jDuration(Duration $duration): self
    {
        return new self('CypherDuration', [
            'months' => $duration->getMonths(),
            'days' => $duration->getDays(),
            'seconds' => $duration->getSeconds(),
            'nanoseconds' => $duration->getNanoseconds(),
        ]);
    }

    /**
     * @return array{hour: int, minute: int, second: int, nanosecond: int}
     */
    private static function decomposeNanosecondsSinceMidnight(int $nanoseconds): array
    {
        $hour = intdiv($nanoseconds, 3_600_000_000_000);
        $remainder = $nanoseconds % 3_600_000_000_000;
        $minute = intdiv($remainder, 60_000_000_000);
        $remainder %= 60_000_000_000;
        $second = intdiv($remainder, 1_000_000_000);
        $nanosecond = $remainder % 1_000_000_000;

        return [
            'hour' => $hour,
            'minute' => $minute,
            'second' => $second,
            'nanosecond' => $nanosecond,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private static function dateTimeToNutkitFields(DateTimeInterface $dt, bool $includeOffset): array
    {
        $fields = [
            'year' => (int) $dt->format('Y'),
            'month' => (int) $dt->format('n'),
            'day' => (int) $dt->format('j'),
            'hour' => (int) $dt->format('G'),
            'minute' => (int) $dt->format('i'),
            'second' => (int) $dt->format('s'),
            'nanosecond' => (int) $dt->format('u') * 1000,
        ];

        if (!$includeOffset) {
            return $fields;
        }

        $fields['utc_offset_s'] = $dt->getOffset();

        $tzName = $dt->getTimezone()->getName();
        if (!in_array($tzName, ['UTC', 'Z', 'GMT'], true)
            && !str_starts_with($tzName, '+')
            && !str_starts_with($tzName, '-')) {
            $fields['timezone_id'] = $tzName;
        }

        return $fields;
    }

    /**
     * @return array{name: string, data: array<string, int|string|null>}
     */
    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'data' => $this->data,
        ];
    }
}
