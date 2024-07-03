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
use DateInterval;
use Exception;
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;

/**
 * A temporal range represented in months, days, seconds and nanoseconds.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<int, int>
 */
final class Duration extends AbstractPropertyObject implements BoltConvertibleInterface
{
    public function __construct(
        private readonly int $months,
        private readonly int $days,
        private readonly int $seconds,
        private readonly int $nanoseconds
    ) {}

    /**
     * The amount of months in the duration.
     */
    public function getMonths(): int
    {
        return $this->months;
    }

    /**
     * The amount of days in the duration after the months have passed.
     */
    public function getDays(): int
    {
        return $this->days;
    }

    /**
     * The amount of seconds in the duration after the days have passed.
     */
    public function getSeconds(): int
    {
        return $this->seconds;
    }

    /**
     * The amount of nanoseconds in the duration after the seconds have passed.
     */
    public function getNanoseconds(): int
    {
        return $this->nanoseconds;
    }

    /**
     * Casts to a DateInterval object.
     *
     * @throws Exception
     */
    public function toDateInterval(): DateInterval
    {
        return new DateInterval(sprintf('P%dM%dDT%dS', $this->months, $this->days, $this->seconds));
    }

    /**
     * @return array{months: int, days: int, seconds: int, nanoseconds: int}
     */
    public function toArray(): array
    {
        return [
            'months' => $this->months,
            'days' => $this->days,
            'seconds' => $this->seconds,
            'nanoseconds' => $this->nanoseconds,
        ];
    }

    public function getProperties(): CypherMap
    {
        return new CypherMap($this);
    }

    public function convertToBolt(): IStructure
    {
        return new \Bolt\protocol\v1\structures\Duration(
            $this->getMonths(),
            $this->getDays(),
            $this->getSeconds(),
            $this->getNanoseconds()
        );
    }
}
