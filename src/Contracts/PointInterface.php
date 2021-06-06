<?php
declare(strict_types=1);


namespace Laudis\Neo4j\Contracts;


interface PointInterface
{
    public function getX(): float;

    public function getY(): float;

    /**
     * @return 'wgs-84'|'wgs-84-3d'|'cartesian'|'cartesian-3d'
     */
    public function getCrs(): string;

    public function getSrid(): int;
}
