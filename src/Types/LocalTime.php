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

final class LocalTime extends AbstractCypherContainer
{
    private int $nanoseconds;

    public function __construct(int $nanoseconds)
    {
        $this->nanoseconds = $nanoseconds;
    }

    public function getNanoseconds(): int
    {
        return $this->nanoseconds;
    }

    public function getIterator()
    {
        yield 'nanoseconds' => $this->nanoseconds;
    }
}
