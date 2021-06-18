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

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Laudis\Neo4j\Exception\TimezoneOffsetException;
use function sprintf;

final class DateTime extends AbstractCypherContainer
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

    public function getSeconds(): int
    {
        return $this->seconds;
    }

    public function getNanoseconds(): int
    {
        return $this->nanoseconds;
    }

    public function getTimeZoneOffsetSeconds(): int
    {
        return $this->tzOffsetSeconds;
    }

    /**
     * @throws Exception
     */
    public function toDateTime(): DateTimeImmutable
    {
        /** @psalm-suppress PossiblyFalseIterator */
        foreach (DateTimeZone::listAbbreviations() as $tz) {
            /** @psalm-suppress all */
            if ($tz[0]['offset'] === $this->getTimeZoneOffsetSeconds()) {
                return (new DateTimeImmutable(sprintf('@%s', $this->getSeconds())))
                    ->modify(sprintf('+%s microseconds', $this->nanoseconds / 1000))
                    ->setTimezone(new DateTimeZone($tz[0]['timezone_id']));
            }
        }

        $message = sprintf('Cannot find an timezone with %s seconds as offset.', $this->tzOffsetSeconds);
        throw new TimezoneOffsetException($message);
    }

    public function getIterator()
    {
        yield 'seconds' => $this->seconds;
        yield 'nanoseconds' => $this->nanoseconds;
        yield 'tzOffsetSeconds' => $this->tzOffsetSeconds;
    }
}
