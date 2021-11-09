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

/**
 * A time object represented in seconds since the unix epoch.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<float, float>
 */
final class Time extends AbstractPropertyObject
{
    private float $seconds;

    public function __construct(float $seconds)
    {
        $this->seconds = $seconds;
    }

    /**
     * The seconds since the unix epoch.
     */
    public function getSeconds(): float
    {
        return $this->seconds;
    }

    /**
     * @return array{seconds: float}
     */
    public function toArray(): array
    {
        return ['seconds' => $this->seconds];
    }

    public function getProperties(): CypherMap
    {
        return new CypherMap($this);
    }
}
