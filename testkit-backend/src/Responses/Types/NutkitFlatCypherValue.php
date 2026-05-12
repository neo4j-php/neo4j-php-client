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
use Laudis\Neo4j\Types\DateTime as Neo4jDateTime;
use Laudis\Neo4j\Types\DateTimeZoneId as Neo4jDateTimeZoneId;

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

    public static function cypherDateTimeFromDateTimeInterface(DateTimeInterface $dt): self
    {
        return new self('CypherDateTime', self::dateTimeToNutkitFields($dt));
    }

    public static function cypherDateTimeFromNeo4jDateTime(Neo4jDateTime $dt): self
    {
        return self::cypherDateTimeFromDateTimeInterface($dt->toDateTime());
    }

    public static function cypherDateTimeFromNeo4jDateTimeZoneId(Neo4jDateTimeZoneId $dt): self
    {
        return self::cypherDateTimeFromDateTimeInterface($dt->toDateTime());
    }

    /**
     * @return array<string, int|string|null>
     */
    private static function dateTimeToNutkitFields(DateTimeInterface $dt): array
    {
        $nanosecond = (int) $dt->format('u') * 1000;
        $tzName = $dt->getTimezone()->getName();
        $utcOffsetS = $dt->getOffset();
        $timezoneId = null;
        if (!in_array($tzName, ['UTC', 'Z', 'GMT'], true)
            && !str_starts_with($tzName, '+')
            && !str_starts_with($tzName, '-')) {
            $timezoneId = $tzName;
        }

        return [
            'year' => (int) $dt->format('Y'),
            'month' => (int) $dt->format('n'),
            'day' => (int) $dt->format('j'),
            'hour' => (int) $dt->format('G'),
            'minute' => (int) $dt->format('i'),
            'second' => (int) $dt->format('s'),
            'nanosecond' => $nanosecond,
            'utc_offset_s' => $utcOffsetS,
            'timezone_id' => $timezoneId,
        ];
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'data' => $this->data,
        ];
    }
}
