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
use Bolt\protocol\v1\structures\Point3D;
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;
use Laudis\Neo4j\Contracts\PointInterface;

/**
 * A cartesian point in three-dimensional space.
 *
 * @see https://neo4j.com/docs/cypher-manual/current/functions/spatial/#functions-point-cartesian-3d
 *
 * @psalm-immutable
 *
 * @psalm-import-type Crs from PointInterface
 */
abstract class Abstract3DPoint extends AbstractPoint implements PointInterface, BoltConvertibleInterface
{
    public function convertToBolt(): IStructure
    {
        return new Point3D($this->getSrid(), $this->getX(), $this->getY(), $this->getZ());
    }

    public function __construct(
        float $x,
        float $y,
        private float $z
    ) {
        parent::__construct($x, $y);
    }

    public function getZ(): float
    {
        return $this->z;
    }

    /**
     * @psalm-suppress ImplementedReturnTypeMismatch
     *
     * @return array{x: float, y: float, z: float, srid: int, crs: Crs}
     */
    public function toArray(): array
    {
        $tbr = parent::toArray();

        $tbr['z'] = $this->z;

        return $tbr;
    }
}
