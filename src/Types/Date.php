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

use Bolt\structures\Date as BoltDate;

class Date
{
    private int $days;

    public function __construct(int $days)
    {
        $this->days = $days;
    }

    public function days(): int
    {
        return $this->days;
    }

    public static function makeFromBoltDate(BoltDate $date): self
    {
        return new self($date->days());
    }
}
