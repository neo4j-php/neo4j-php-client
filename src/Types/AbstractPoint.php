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

use Bolt\protocol\IStructure;
use Bolt\protocol\v1\structures\Point2D;
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;
use Laudis\Neo4j\Contracts\PointInterface;

/**
 * A cartesian point in two-dimensional space.
 *
 * @see https://neo4j.com/docs/cypher-manual/current/functions/spatial/#functions-point-cartesian-2d
 *
 * @psalm-immutable
 *
 * @psalm-import-type Crs from PointInterface
 *
 * @extends AbstractPropertyObject<float|int|string, float|int|string>
 */
abstract class AbstractPoint extends AbstractPropertyObject implements PointInterface, BoltConvertibleInterface
{
    public function __construct(
        private readonly float $x,
        private readonly float $y
    ) {}

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
