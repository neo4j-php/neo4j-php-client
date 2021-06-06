<?php
declare(strict_types=1);


namespace Laudis\Neo4j\Types;


final class LocalDateTime
{
    private int $seconds;
    private int $nanoseconds;

    public function __construct(int $seconds, int $nanoseconds)
    {
        $this->seconds = $seconds;
        $this->nanoseconds = $nanoseconds;
    }

    public function getSeconds(): int
    {
        return $this->seconds;
    }

    public function getNanoseconds(): int
    {
        return $this->nanoseconds;
    }
}
