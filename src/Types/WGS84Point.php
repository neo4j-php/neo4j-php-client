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
final class WGS84Point extends CartesianPoint implements PointInterface
{
    private float $latitude;
    private float $longitude;

    /**
     * @param Crs $crs
     */
    public function __construct(float $latitude, float $longitude, float $x, float $y, string $crs, int $srid)
    {
        parent::__construct($x, $y, $crs, $srid);
        $this->latitude = $latitude;
        $this->longitude = $longitude;
    }

    /**
     * A numeric expression that represents the latitude/y value in decimal degrees.
     */
    public function getLatitude(): float
    {
        return $this->latitude;
    }

    /**
     * A numeric expression that represents the longitude/x value in decimal degrees.
     */
    public function getLongitude(): float
    {
        return $this->longitude;
    }

    /**
     * @return array{
     *                latitude: float,
     *                longitude: float,
     *                x: float,
     *                y: float,
     *                crs: Crs,
     *                srid: int
     *                }
     */
    public function toArray(): array
    {
        $tbr = parent::toArray();

        $tbr['longitude'] = $this->longitude;
        $tbr['latitude'] = $this->latitude;

        return $tbr;
    }
}
