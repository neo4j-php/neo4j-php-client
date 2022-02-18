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

use Bolt\structures\IStructure;
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;

/**
 * A time object represented in seconds since the unix epoch.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<float, float>
 */
final class Time extends AbstractPropertyObject implements BoltConvertibleInterface
{
    private int $nanoSeconds;
    private int $tzOffsetSeconds;

    public function __construct(int $nanoSeconds, int $tzOffsetSeconds)
    {
        $this->nanoSeconds = $nanoSeconds;
        $this->tzOffsetSeconds = $tzOffsetSeconds;
    }

    /**
     * @return array{nanoSeconds: int, tzOffsetSeconds: int}
     */
    public function toArray(): array
    {
        return ['nanoSeconds' => $this->nanoSeconds, 'tzOffsetSeconds' => $this->tzOffsetSeconds];
    }

    public function getTzOffsetSeconds(): int
    {
        return $this->tzOffsetSeconds;
    }

    public function getNanoSeconds(): int
    {
        return $this->nanoSeconds;
    }

    public function getProperties(): CypherMap
    {
        return new CypherMap($this);
    }

    public function convertToBolt(): IStructure
    {
        return new \Bolt\structures\Time($this->getNanoSeconds(), $this->getTzOffsetSeconds());
    }
}
