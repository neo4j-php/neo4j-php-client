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

use JsonSerializable;
use Laudis\Neo4j\Contracts\PointInterface;

final class CartesianPoint implements PointInterface, JsonSerializable
{
    private float $x;
    private float $y;
    /** @var 'wgs-84'|'wgs-84-3d'|'cartesian'|'cartesian-3d' */
    private string $crs;
    private int $srid;

    /**
     * @param 'wgs-84'|'wgs-84-3d'|'cartesian'|'cartesian-3d' $crs
     */
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

    public function jsonSerialize()
    {
        return [
            'x' => $this->getX(),
            'y' => $this->getY(),
            'crs' => $this->getCrs(),
            'srid' => $this->getSrid(),
        ];
    }
}
