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
use DateTimeZone;
use Exception;
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;
use Laudis\Neo4j\Enum\ConnectionProtocol;

use function sprintf;

/**
 * A date represented by seconds and nanoseconds since unix epoch, enriched with a timezone offset in seconds.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<int, int>
 */
final class DateTime extends AbstractPropertyObject implements BoltConvertibleInterface
{
    public function __construct(
        private readonly int $seconds,
        private readonly int $nanoseconds,
        private readonly int $tzOffsetSeconds,
        private readonly bool $legacy,
    ) {
    }

    /**
     * Returns whether this DateTime Type follows conventions up until Neo4j version 4.
     */
    public function isLegacy(): bool
    {
        return $this->legacy;
    }

    /**
     * Returns the amount of seconds since unix epoch.
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
     * Returns the timezone offset in seconds.
     */
    public function getTimeZoneOffsetSeconds(): int
    {
        return $this->tzOffsetSeconds;
    }

    /**
     * Casts to an immutable date time.
     *
     * @throws Exception
     */
    public function toDateTime(): DateTimeImmutable
    {
        $dateTime = new DateTimeImmutable(sprintf('@%s', $this->getSeconds()));
        $dateTime = $dateTime->modify(sprintf('+%s microseconds', $this->nanoseconds / 1000));
        /** @psalm-suppress PossiblyFalseReference, ImpureMethodCall */
        $dateTime = $dateTime->setTimezone(self::fixedOffsetTimeZone($this->getTimeZoneOffsetSeconds()));

        if ($this->legacy) {
            /**
             * @psalm-suppress FalsableReturnStatement
             *
             * @var DateTimeImmutable
             */
            return $dateTime->modify(sprintf('-%s seconds', $this->getTimeZoneOffsetSeconds()));
        }

        /** @var DateTimeImmutable */
        return $dateTime;
    }

    /**
     * PHP {@see DateTimeZone} needs a real offset form such as "+00:30", not a mangled HHMM integer
     * (e.g. offset1800s must not become "+0050", which is 50 minutes and breaks TestKit offset-only datetimes).
     */
    private static function fixedOffsetTimeZone(int $tzOffsetSeconds): DateTimeZone
    {
        $sign = $tzOffsetSeconds < 0 ? '-' : '+';
        $abs = abs($tzOffsetSeconds);
        $hours = intdiv($abs, 3600);
        $minutes = intdiv($abs % 3600, 60);
        $seconds = $abs % 60;
        $name = $seconds === 0
            ? sprintf('%s%02d:%02d', $sign, $hours, $minutes)
            : sprintf('%s%02d:%02d:%02d', $sign, $hours, $minutes, $seconds);

        /** @var non-empty-string $name */
        return new DateTimeZone($name);
    }

    /**
     * @return array{seconds: int, nanoseconds: int, tzOffsetSeconds: int}
     */
    public function toArray(): array
    {
        return [
            'seconds' => $this->seconds,
            'nanoseconds' => $this->nanoseconds,
            'tzOffsetSeconds' => $this->tzOffsetSeconds,
        ];
    }

    public function getProperties(): CypherMap
    {
        return new CypherMap($this);
    }

    public function convertToBolt(): IStructure
    {
        if ($this->legacy) {
            return new \Bolt\protocol\v1\structures\DateTime($this->getSeconds(), $this->getNanoseconds(), $this->getTimeZoneOffsetSeconds());
        }

        return new \Bolt\protocol\v5\structures\DateTime($this->getSeconds(), $this->getNanoseconds(), $this->getTimeZoneOffsetSeconds());
    }

    /**
     * Legacy 0x46 for Bolt below 5 when the UTC patch is not negotiated (incl. 4.4 non-patched TestKit stubs).
     * v5/0x49 for Bolt 5+ or when the server echoed patch_bolt utc (4.3–4.4).
     */
    public function convertToBoltWithProtocol(
        ConnectionProtocol $protocol,
        bool $boltUtcPatchNegotiated = false,
    ): IStructure {
        /** @psalm-suppress ImpureMethodCall */
        $isLegacyWire = $protocol->compare(ConnectionProtocol::BOLT_V5()) < 0 && !$boltUtcPatchNegotiated;
        if ($isLegacyWire) {
            // Legacy Bolt DateTime (0x46): seconds are UTC epoch + tz offset, not plain Unix UTC.
            return new \Bolt\protocol\v1\structures\DateTime(
                $this->getSeconds() + $this->getTimeZoneOffsetSeconds(),
                $this->getNanoseconds(),
                $this->getTimeZoneOffsetSeconds(),
            );
        }

        return new \Bolt\protocol\v5\structures\DateTime(
            $this->getSeconds(),
            $this->getNanoseconds(),
            $this->getTimeZoneOffsetSeconds(),
        );
    }
}
