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

final class Time extends AbstractCypherContainer
{
    private float $seconds;

    public function __construct(float $seconds)
    {
        $this->seconds = $seconds;
    }

    public function getSeconds(): float
    {
        return $this->seconds;
    }

    public function getIterator()
    {
        yield 'seconds' => $this->seconds;
    }
}
