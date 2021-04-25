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
use Bolt\structures\Duration as BoltDuration;

class Duration
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

    public function months(): int
    {
        return $this->months;
    }

    public function days(): int
    {
        return $this->days;
    }

    public function seconds(): int
    {
        return $this->seconds;
    }

    public function nanoseconds(): int
    {
        return $this->nanoseconds;
    }

    public function toDateInterval(): DateInterval
    {
        return new DateInterval(sprintf('P%dM%dDT%dS', $this->months, $this->days, $this->seconds));
    }

    public static function makeFromBoltDuration(BoltDuration $duration): self
    {
        return new self(
            $duration->months(),
            $duration->days(),
            $duration->seconds(),
            $duration->nanoseconds(),
        );
    }
}
