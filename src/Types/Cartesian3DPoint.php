<?php
declare(strict_types=1);


namespace Laudis\Neo4j\Types;


use Laudis\Neo4j\Contracts\PointInterface;

final class Cartesian3DPoint implements PointInterface
{
    private float $z;
    private float $x;
    private float $y;
    private string $crs;
    private int $srid;

    public function __construct(float $x, float $y, float $z, string $crs, int $srid)
    {
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
