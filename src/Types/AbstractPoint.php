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

use Bolt\structures\IStructure;
use Bolt\structures\Point2D;
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;
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
abstract class AbstractPoint extends AbstractPropertyObject implements PointInterface, BoltConvertibleInterface
{
    private float $x;
    private float $y;

    /**
     * @param Crs $crs
     */
    public function __construct(float $x, float $y)
    {
        $this->x = $x;
        $this->y = $y;
    }

    abstract public function getCrs(): string;

    abstract public function getSrid(): int;

    public function convertToBolt(): IStructure
    {
        return new Point2D($this->getSrid(), $this->getX(), $this->getY());
    }

    public function getX(): float
    {
        return $this->x;
    }

    public function getY(): float
    {
        return $this->y;
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
            'crs' => $this->getCrs(),
            'srid' => $this->getSrid(),
        ];
    }
}
