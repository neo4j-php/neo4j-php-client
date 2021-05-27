<?php
declare(strict_types=1);


namespace Laudis\Neo4j\Types;


final class Time
{
    private float $seconds;

    public function __construct(float $seconds)
    {
        $this->seconds = $seconds;
    }

    public function getSeconds(): float
    {
        return $this->seconds;
    }
}
