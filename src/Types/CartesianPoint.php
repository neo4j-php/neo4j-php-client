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
 * A cartesian point in two dimensional space.
 *
 * @see https://neo4j.com/docs/cypher-manual/current/functions/spatial/#functions-point-cartesian-2d
 *
 * @psalm-immutable
 *
 * @psalm-import-type Crs from \Laudis\Neo4j\Contracts\PointInterface
 */
class CartesianPoint extends AbstractPropertyObject implements PointInterface
{
    private float $x;
    private float $y;
    /** @var Crs */
    private string $crs;
    private int $srid;

    /**
     * @param Crs $crs
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

    public function getProperties(): CypherMap
    {
        /** @psalm-suppress InvalidReturnStatement False positive */
        return new CypherMap($this);
    }

    /**
     * @psalm-suppress ImplementedReturnTypeMismatch False positive
     *
     * @return array{x: float, y: float, crs: Crs, srid: int}
     */
    public function toArray(): array
    {
        return [
            'x' => $this->x,
            'y' => $this->y,
            'crs' => $this->crs,
            'srid' => $this->srid,
        ];
    }
}
