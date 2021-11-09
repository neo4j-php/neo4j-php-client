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
 * A WGS84 Point in three dimensional space.
 *
 * @see https://neo4j.com/docs/cypher-manual/current/functions/spatial/#functions-point-wgs84-3d
 *
 * @psalm-immutable
 *
 * @psalm-import-type Crs from \Laudis\Neo4j\Contracts\PointInterface
 */
final class WGS843DPoint extends Cartesian3DPoint implements PointInterface
{
    private float $latitude;
    private float $longitude;
    private float $height;

    /**
     * @param Crs $crs
     */
    public function __construct(float $latitude, float $longitude, float $height, float $x, float $y, float $z, string $crs, int $srid)
    {
        parent::__construct($x, $y, $z, $crs, $srid);
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->height = $height;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getHeight(): float
    {
        return $this->height;
    }

    /**
     * @psalm-suppress ImplementedReturnTypeMismatch False positive
     *
     * @return array{
     *                latitude: float,
     *                longitude: float,
     *                height: float,
     *                x: float,
     *                y: float,
     *                z: float,
     *                crs: Crs,
     *                srid: int
     *                }
     */
    public function toArray(): array
    {
        $tbr = parent::toArray();

        $tbr['latitude'] = $this->latitude;
        $tbr['longitude'] = $this->longitude;
        $tbr['height'] = $this->height;

        return $tbr;
    }
}
