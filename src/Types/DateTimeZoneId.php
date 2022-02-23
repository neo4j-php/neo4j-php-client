<?php

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
use function sprintf;

/**
 * A date represented by seconds and nanoseconds since unix epoch, enriched with a timezone identifier.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<int|string, int|string>
 */
final class DateTimeZoneId extends AbstractPropertyObject implements BoltConvertibleInterface
{
    private int $seconds;
    private int $nanoseconds;
    private string $tzId;

    public function __construct(int $seconds, int $nanoseconds, string $tzId)
    {
        $this->seconds = $seconds;
        $this->nanoseconds = $nanoseconds;
        $this->tzId = $tzId;
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
     */
    public function toDateTime(): DateTimeImmutable
    {
        return (new DateTimeImmutable(sprintf('@%s', $this->getSeconds())))
                    ->modify(sprintf('+%s microseconds', $this->nanoseconds / 1000))
                    ->setTimezone(new DateTimeZone($this->tzId));
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
        return new \Bolt\structures\DateTimeZoneId($this->getSeconds(), $this->getNanoseconds(), $this->getTimezoneIdentifier());
    }
}
