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

use function intdiv;

use Laudis\Neo4j\Contracts\BoltConvertibleInterface;

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
        /** @psalm-suppress ImpureMethodCall, ArgumentTypeCoercion */
        $dateTime = $dateTime->setTimezone(new DateTimeZone(self::fixedOffsetTimezoneIdFromSeconds($this->getTimeZoneOffsetSeconds())));

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
     * PHP {@see DateTimeZone} accepts fixed offsets like {@code +00:30}; the old {@code sprintf("%+'05d", …)}
     * encoding only worked for whole-hour offsets and broke civil time for e.g. +30 minutes.
     */
    private static function fixedOffsetTimezoneIdFromSeconds(int $offsetSeconds): string
    {
        $sign = $offsetSeconds >= 0 ? '+' : '-';
        $abs = abs($offsetSeconds);
        $h = intdiv($abs, 3600);
        $remainder = $abs % 3600;
        $m = intdiv($remainder, 60);
        $s = $remainder % 60;
        if ($s !== 0) {
            return sprintf('%s%02d:%02d:%02d', $sign, $h, $m, $s);
        }

        return sprintf('%s%02d:%02d', $sign, $h, $m);
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
}
