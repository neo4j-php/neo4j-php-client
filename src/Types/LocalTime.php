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
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;

/**
 * The time of day represented in nanoseconds.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<int, int>
 */
final class LocalTime extends AbstractPropertyObject implements BoltConvertibleInterface
{
    public function __construct(
        private readonly int $nanoseconds
    ) {}

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

    public function convertToBolt(): IStructure
    {
        return new \Bolt\protocol\v1\structures\LocalTime($this->getNanoseconds());
    }
}
