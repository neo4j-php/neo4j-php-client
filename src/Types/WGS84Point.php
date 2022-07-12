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
 * A WGS84 Point in two dimensional space.
 *
 * @psalm-immutable
 *
 * @see https://neo4j.com/docs/cypher-manual/current/functions/spatial/#functions-point-wgs84-2d
 *
 * @psalm-import-type Crs from \Laudis\Neo4j\Contracts\PointInterface
 */
final class WGS84Point extends AbstractPoint implements PointInterface, BoltConvertibleInterface
{
    public const SRID = 4326;
    public const CRS = 'wgs-84';

    public function getSrid(): int
    {
        return self::SRID;
    }

    public function getCrs(): string
    {
        return self::CRS;
    }

    /**
     * A numeric expression that represents the longitude/x value in decimal degrees.
     */
    public function getLongitude(): float
    {
        return $this->getX();
    }

    /**
     * A numeric expression that represents the latitude/y value in decimal degrees.
     */
    public function getLatitude(): float
    {
        return $this->getY();
    }
}
