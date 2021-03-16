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

use Bolt\structures\DateTime as BoltDateTimeAlias;

class DateTime
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

    public function seconds(): int
    {
        return $this->seconds;
    }

    public function nanoseconds(): int
    {
        return $this->nanoseconds;
    }

    public function offsetSeconds(): int
    {
        return $this->tzOffsetSeconds;
    }

    public static function makeFromBoltDateTime(BoltDateTimeAlias $datetime): self
    {
        return new self($datetime->seconds(), $datetime->nanoseconds(), $datetime->tz_offset_seconds());
    }
}
