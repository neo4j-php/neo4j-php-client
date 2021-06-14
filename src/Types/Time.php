<?php
declare(strict_types=1);


namespace Laudis\Neo4j\Types;


use JsonSerializable;

final class Time implements JsonSerializable
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

    public function jsonSerialize()
    {
        return [
            'seconds' => $this->seconds
        ];
    }
}
