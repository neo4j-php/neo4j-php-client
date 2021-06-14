<?php
declare(strict_types=1);


namespace Laudis\Neo4j\Types;


use JsonSerializable;

final class LocalTime implements JsonSerializable
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

    public function jsonSerialize()
    {
        return [
            'nanoseconds' => $this->nanoseconds
        ];
    }
}
