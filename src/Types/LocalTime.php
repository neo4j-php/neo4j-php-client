<?php
declare(strict_types=1);


namespace Laudis\Neo4j\Types;


final class LocalTime
{
    private int $nanoseconds;

    public function __construct(int $nanoseconds)
    {
        $this->nanoseconds = $nanoseconds;
    }

    public function getNanoseconds(): int
    {
        return $this->nanoseconds;
    }
}
