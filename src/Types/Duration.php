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

use DateInterval;
use Exception;

final class Duration extends AbstractCypherContainer
{
    private int $months;
    private int $days;
    private int $seconds;
    private int $nanoseconds;

    public function __construct(int $months, int $days, int $seconds, int $nanoseconds)
    {
        $this->months = $months;
        $this->days = $days;
        $this->seconds = $seconds;
        $this->nanoseconds = $nanoseconds;
    }

    public function getMonths(): int
    {
        return $this->months;
    }

    public function getDays(): int
    {
        return $this->days;
    }

    public function getSeconds(): int
    {
        return $this->seconds;
    }

    public function getNanoseconds(): int
    {
        return $this->nanoseconds;
    }

    /**
     * @throws Exception
     */
    public function toDateInterval(): DateInterval
    {
        return new DateInterval(sprintf('P%dM%dDT%dS', $this->months, $this->days, $this->seconds));
    }

    public function getIterator()
    {
        yield 'months' => $this->months;
        yield 'days' => $this->days;
        yield 'seconds' => $this->seconds;
        yield 'nanoseconds' => $this->nanoseconds;
    }
}
