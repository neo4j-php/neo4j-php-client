<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Types;

use Bolt\structures\IStructure;
use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;
use RuntimeException;
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
    private int $seconds;
    private int $nanoseconds;
    private int $tzOffsetSeconds;

    public function __construct(int $seconds, int $nanoseconds, int $tzOffsetSeconds)
    {
        $this->seconds = $seconds;
        $this->nanoseconds = $nanoseconds;
        $this->tzOffsetSeconds = $tzOffsetSeconds;
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
        /** @psalm-suppress all */
        foreach (DateTimeZone::listAbbreviations() as $tz) {
            /** @psalm-suppress all */
            if ($tz[0]['offset'] === $this->getTimeZoneOffsetSeconds()) {
                return (new DateTimeImmutable(sprintf('@%s', $this->getSeconds())))
                    ->modify(sprintf('+%s microseconds', $this->nanoseconds / 1000))
                    ->setTimezone(new DateTimeZone($tz[0]['timezone_id']));
            }
        }

        $message = sprintf('Cannot find an timezone with %s seconds as offset.', $this->tzOffsetSeconds);
        throw new RuntimeException($message);
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
        return new \Bolt\structures\DateTime($this->getSeconds(), $this->getNanoseconds(), $this->getTimeZoneOffsetSeconds());
    }
}
