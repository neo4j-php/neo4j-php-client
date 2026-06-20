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

namespace Laudis\Neo4j\Tests\Unit;

use Laudis\Neo4j\TestkitBackend\NutkitValueDecoder;
use Laudis\Neo4j\TestkitBackend\Responses\Types\CypherObject;
use Laudis\Neo4j\TestkitBackend\Responses\Types\NutkitFlatCypherValue;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\DateTimeZoneId;
use Laudis\Neo4j\Types\Duration;
use Laudis\Neo4j\Types\LocalDateTime;
use Laudis\Neo4j\Types\LocalTime;
use Laudis\Neo4j\Types\Time;
use PHPUnit\Framework\TestCase;

final class NutkitTemporalTypesTest extends TestCase
{
    /**
     * @return array{name: string, data: array<string, int|string|null>}
     */
    private static function flatJson(NutkitFlatCypherValue $value): array
    {
        return $value->jsonSerialize();
    }

    public function testLocalDateTimeOutboundOmitsOffsetFields(): void
    {
        $local = new LocalDateTime(262224896, 123000000);
        $dt = $local->toDateTime();
        $encoded = self::flatJson(NutkitFlatCypherValue::cypherDateTimeFromNeo4jLocalDateTime($local));

        self::assertSame('CypherDateTime', $encoded['name']);
        self::assertSame([
            'year' => (int) $dt->format('Y'),
            'month' => (int) $dt->format('n'),
            'day' => (int) $dt->format('j'),
            'hour' => (int) $dt->format('G'),
            'minute' => (int) $dt->format('i'),
            'second' => (int) $dt->format('s'),
            'nanosecond' => 123000000,
        ], $encoded['data']);
        self::assertArrayNotHasKey('utc_offset_s', $encoded['data']);
        self::assertArrayNotHasKey('timezone_id', $encoded['data']);
    }

    public function testZonedDateTimeOutboundIncludesOffset(): void
    {
        $zoned = new DateTime(1680259200, 0, 7200, false);
        $encoded = self::flatJson(NutkitFlatCypherValue::cypherDateTimeFromNeo4jDateTime($zoned));

        self::assertTrue(array_key_exists('utc_offset_s', $encoded['data']));
        self::assertSame(7200, $encoded['data']['utc_offset_s']);
        self::assertArrayNotHasKey('timezone_id', $encoded['data']);
    }

    public function testNamedZoneDateTimeOutboundIncludesTimezoneId(): void
    {
        $zoned = new DateTimeZoneId(1680259200, 0, 'Europe/Paris');
        $encoded = self::flatJson(NutkitFlatCypherValue::cypherDateTimeFromNeo4jDateTimeZoneId($zoned));

        self::assertTrue(array_key_exists('timezone_id', $encoded['data']));
        self::assertSame('Europe/Paris', $encoded['data']['timezone_id']);
        self::assertTrue(array_key_exists('utc_offset_s', $encoded['data']));
    }

    public function testDurationOutbound(): void
    {
        $duration = new Duration(3, 4, 999, 123456789);
        $encoded = self::flatJson(NutkitFlatCypherValue::cypherDurationFromNeo4jDuration($duration));

        self::assertSame('CypherDuration', $encoded['name']);
        self::assertSame([
            'months' => 3,
            'days' => 4,
            'seconds' => 999,
            'nanoseconds' => 123456789,
        ], $encoded['data']);
    }

    public function testLocalTimeOutboundOmitsOffset(): void
    {
        $localTime = new LocalTime(12 * 3_600_000_000_000 + 34 * 60_000_000_000 + 56 * 1_000_000_000 + 789012345);
        $encoded = self::flatJson(NutkitFlatCypherValue::cypherTimeFromNeo4jLocalTime($localTime));

        self::assertSame('CypherTime', $encoded['name']);
        self::assertTrue(isset($encoded['data']['hour'], $encoded['data']['nanosecond']));
        self::assertSame(12, $encoded['data']['hour']);
        self::assertSame(789012345, $encoded['data']['nanosecond']);
        self::assertArrayNotHasKey('utc_offset_s', $encoded['data']);
    }

    public function testCypherObjectAutoDetectReturnsFlatTemporalValues(): void
    {
        $value = CypherObject::autoDetect(new LocalDateTime(262224896, 0));

        self::assertInstanceOf(NutkitFlatCypherValue::class, $value);
    }

    public function testCypherListSerializesNestedTemporalValues(): void
    {
        /** @var array{name: string, data: array{value: list<array{name: string}>}} $list */
        $list = CypherObject::autoDetect(new CypherList([
            new Duration(1, 2, 3, 4),
            42,
        ]))->jsonSerialize();

        self::assertSame('CypherList', $list['name']);
        $value = $list['data']['value'];
        self::assertTrue(isset($value[0], $value[1]));
        self::assertSame('CypherDuration', $value[0]['name']);
        self::assertSame('CypherInt', $value[1]['name']);
    }

    public function testDecodeLocalDateTimeParameter(): void
    {
        $decoded = NutkitValueDecoder::decode([
            'name' => 'CypherDateTime',
            'data' => [
                'year' => 1976,
                'month' => 6,
                'day' => 13,
                'hour' => 12,
                'minute' => 34,
                'second' => 56,
                'nanosecond' => 0,
            ],
        ]);

        self::assertInstanceOf(LocalDateTime::class, $decoded);
    }

    public function testDecodeZonedDateTimeParameter(): void
    {
        $decoded = NutkitValueDecoder::decode([
            'name' => 'CypherDateTime',
            'data' => [
                'year' => 2022,
                'month' => 3,
                'day' => 30,
                'hour' => 13,
                'minute' => 24,
                'second' => 34,
                'nanosecond' => 699546224,
                'utc_offset_s' => 7200,
            ],
        ]);

        self::assertInstanceOf(DateTime::class, $decoded);
        self::assertSame(7200, $decoded->getTimeZoneOffsetSeconds());
    }

    public function testDecodeDurationParameter(): void
    {
        $decoded = NutkitValueDecoder::decode([
            'name' => 'CypherDuration',
            'data' => [
                'months' => 3,
                'days' => 4,
                'seconds' => 999,
                'nanoseconds' => 123456789,
            ],
        ]);

        self::assertInstanceOf(Duration::class, $decoded);
        self::assertSame(3, $decoded->getMonths());
    }

    public function testDecodeDateParameter(): void
    {
        $decoded = NutkitValueDecoder::decode([
            'name' => 'CypherDate',
            'data' => [
                'year' => 2022,
                'month' => 3,
                'day' => 30,
            ],
        ]);

        self::assertInstanceOf(Date::class, $decoded);
        $encoded = self::flatJson(NutkitFlatCypherValue::cypherDateFromNeo4jDate($decoded));
        self::assertSame([
            'year' => 2022,
            'month' => 3,
            'day' => 30,
        ], $encoded['data']);
    }

    public function testDecodeLocalTimeParameter(): void
    {
        $decoded = NutkitValueDecoder::decode([
            'name' => 'CypherTime',
            'data' => [
                'hour' => 12,
                'minute' => 34,
                'second' => 56,
                'nanosecond' => 789012345,
            ],
        ]);

        self::assertInstanceOf(LocalTime::class, $decoded);
    }

    public function testDecodeZonedTimeParameter(): void
    {
        $decoded = NutkitValueDecoder::decode([
            'name' => 'CypherTime',
            'data' => [
                'hour' => 12,
                'minute' => 34,
                'second' => 56,
                'nanosecond' => 789012345,
                'utc_offset_s' => 5400,
            ],
        ]);

        self::assertInstanceOf(Time::class, $decoded);
        self::assertSame(5400, $decoded->getTzOffsetSeconds());
    }
}
