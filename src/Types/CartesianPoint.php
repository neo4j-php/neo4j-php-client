<?php
declare(strict_types=1);


namespace Laudis\Neo4j\Types;

use Laudis\Neo4j\Contracts\PointInterface;

final class CartesianPoint implements PointInterface
{
    private float $x;
    private float $y;
    private string $crs;
    private int $srid;

    public function __construct(float $x, float $y, string $crs, int $srid)
    {
        $this->x = $x;
        $this->y = $y;
        $this->crs = $crs;
        $this->srid = $srid;
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
