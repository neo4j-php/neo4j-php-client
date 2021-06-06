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

final class WGS843DPoint implements PointInterface
{
    private float $latitude;
    private float $longitude;
    private float $height;
    private float $x;
    private float $y;
    private float $z;
    private string $crs;
    private int $srid;

    public function __construct(float $latitude, float $longitude, float $height, float $x, float $y, float $z, string $crs, int $srid)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->height = $height;
        $this->x = $x;
        $this->y = $y;
        $this->z = $z;
        $this->crs = $crs;
        $this->srid = $srid;
    }

    public function getZ(): float
    {
        return $this->z;
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

    public function getX(): float
    {
        return $this->x;
    }

    public function getY(): float
    {
        return $this->y;
    }

    public function getCrs(): string
    {
        return $this->crs;
    }

    public function getSrid(): int
    {
        return $this->srid;
    }
}
