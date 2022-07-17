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

namespace Laudis\Neo4j\Contracts;

/**
 * Defines a basic Point type in neo4j.
 *
 * @psalm-immutable
 *
 * @psalm-type Crs = 'wgs-84'|'wgs-84-3d'|'cartesian'|'cartesian-3d';
 */
interface PointInterface
{
    /**
     * Returns the x coordinate.
     */
    public function getX(): float;

    /**
     * Returns the y coordinate.
     */
    public function getY(): float;

    /**
     * Returns the Coordinates Reference System.
     *
     * @see https://en.wikipedia.org/wiki/Spatial_reference_system
     *
     * @return Crs
     */
    public function getCrs(): string;

    /**
     * Returns the spacial reference identifier.
     *
     * @see https://en.wikipedia.org/wiki/Spatial_reference_system
     */
    public function getSrid(): int;
}
