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
 * The time of day represented in nanoseconds.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<int, int>
 */
final class LocalTime extends AbstractPropertyObject
{
    private int $nanoseconds;

    public function __construct(int $nanoseconds)
    {
        $this->nanoseconds = $nanoseconds;
    }

    /**
     * The nanoseconds that have passed since midnight.
     */
    public function getNanoseconds(): int
    {
        return $this->nanoseconds;
    }

    /**
     * @return array{nanoseconds: int}
     */
    public function toArray(): array
    {
        return ['nanoseconds' => $this->nanoseconds];
    }

    public function getProperties(): CypherMap
    {
        return new CypherMap($this);
    }
}
