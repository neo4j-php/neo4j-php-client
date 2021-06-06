<?php
declare(strict_types=1);


namespace Laudis\Neo4j\Types;


use Laudis\Neo4j\Contracts\PointInterface;

final class WGS84Point implements PointInterface
{
    private float $latitude;
    private float $longitude;
    private float $x;
    private float $y;
    private string $crs;
    private int $srid;

    public function __construct(float $latitude, float $longitude, float $x, float $y, string $crs, int $srid)
    {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->x = $x;
        $this->y = $y;
        $this->crs = $crs;
        $this->srid = $srid;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
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
