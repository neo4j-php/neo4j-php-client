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

namespace Laudis\Neo4j\Types;

use Bolt\protocol\IStructure;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;

use function sprintf;

use UnexpectedValueException;

/**
 * Bolt {@code DateTimeZoneId} (≤4.4) first field: SI seconds from {@code 1970-01-01T00:00:00} as UTC-naive
 * to the zone wall clock (same rule as TestKit simple_jolt {@code JoltDateTime.seconds_nanoseconds}).
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<int|string, int|string>
 *
 * @psalm-suppress TypeDoesNotContainType
 */
final class DateTimeZoneId extends AbstractPropertyObject implements BoltConvertibleInterface
{
    /**
     * @param non-empty-string $tzId
     */
    public function __construct(
        private readonly int $seconds,
        private readonly int $nanoseconds,
        private readonly string $tzId,
    ) {
    }

    /**
     * Bolt {@code DateTimeZoneId} “civil” seconds field (see class docblock).
     */
    public function getSeconds(): int
    {
        return $this->seconds;
    }

    /**
     * Returns the amount of nanoseconds after the seconds have passed.
     */
    public function getNanoseconds(): int
    {
        return $this->nanoseconds;
    }

    /**
     * Returns the timezone identifier.
     */
    public function getTimezoneIdentifier(): string
    {
        return $this->tzId;
    }

    /**
     * Casts to an immutable date time.
     *
     * @throws Exception
     *
     * @psalm-suppress ImpureMethodCall
     */
    public function toDateTime(): DateTimeImmutable
    {
        return self::toDateTimeFromBoltCivilSeconds($this->seconds, $this->nanoseconds, $this->tzId);
    }

    /**
     * Encodes a zoned instant into Bolt ≤4.4 {@code DateTimeZoneId} first integer (TestKit/simple_jolt compatible).
     */
    public static function encodeBoltCivilSecondsForInstant(DateTimeInterface $instant, DateTimeZone $tz): int
    {
        $immutable = $instant instanceof DateTimeImmutable
            ? $instant->setTimezone($tz)
            : DateTimeImmutable::createFromMutable($instant)->setTimezone($tz);
        $wall = $immutable->format('Y-m-d H:i:s');
        $naiveWallAsUtc = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $wall, new DateTimeZone('UTC'));
        if ($naiveWallAsUtc === false) {
            throw new UnexpectedValueException('Expected DateTimeImmutable');
        }
        $naiveEpoch = new DateTimeImmutable('1970-01-01 00:00:00', new DateTimeZone('UTC'));

        return $naiveWallAsUtc->getTimestamp() - $naiveEpoch->getTimestamp();
    }

    /**
     * @param non-empty-string $tzId
     */
    public static function toDateTimeFromBoltCivilSeconds(int $seconds, int $nanoseconds, string $tzId): DateTimeImmutable
    {
        $tz = new DateTimeZone($tzId);
        $naiveUtc = (new DateTimeImmutable('1970-01-01 00:00:00', new DateTimeZone('UTC')))
            ->modify(sprintf('%+d seconds', $seconds));
        if ($naiveUtc === false) {
            throw new UnexpectedValueException('Expected DateTimeImmutable');
        }
        $wall = $naiveUtc->format('Y-m-d H:i:s');
        $instant = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $wall, $tz);
        if ($instant === false) {
            throw new UnexpectedValueException('Expected DateTimeImmutable');
        }
        $withNanos = $instant->modify(sprintf('%+d microseconds', intdiv($nanoseconds, 1000)));
        if ($withNanos === false) {
            throw new UnexpectedValueException('Expected DateTimeImmutable');
        }

        return $withNanos;
    }

    /**
     * @return array{seconds: int, nanoseconds: int, tzId: string}
     */
    public function toArray(): array
    {
        return [
            'seconds' => $this->seconds,
            'nanoseconds' => $this->nanoseconds,
            'tzId' => $this->tzId,
        ];
    }

    /**
     * @return CypherMap<string|int>
     */
    public function getProperties(): CypherMap
    {
        return new CypherMap($this);
    }

    public function convertToBolt(): IStructure
    {
        return new \Bolt\protocol\v1\structures\DateTimeZoneId($this->getSeconds(), $this->getNanoseconds(), $this->getTimezoneIdentifier());
    }
}
