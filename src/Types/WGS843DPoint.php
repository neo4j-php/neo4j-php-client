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

use Laudis\Neo4j\Contracts\BoltConvertibleInterface;
use Laudis\Neo4j\Contracts\PointInterface;

/**
 * A WGS84 Point in three-dimensional space.
 *
 * @see https://neo4j.com/docs/cypher-manual/current/functions/spatial/#functions-point-wgs84-3d
 *
 * @psalm-immutable
 *
 * @psalm-import-type Crs from PointInterface
 */
final class WGS843DPoint extends Abstract3DPoint implements PointInterface, BoltConvertibleInterface
{
    public const SRID = 4979;
    public const CRS = 'wgs-84-3d';

    public function getSrid(): int
    {
        return self::SRID;
    }

    public function getLongitude(): float
    {
        return $this->getX();
    }

    public function getLatitude(): float
    {
        return $this->getY();
    }

    public function getHeight(): float
    {
        return $this->getZ();
    }

    public function getCrs(): string
    {
        return self::CRS;
    }
}
