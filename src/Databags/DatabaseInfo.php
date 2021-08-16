<?php
declare(strict_types=1);


namespace Laudis\Neo4j\Databags;


final class DatabaseInfo
{
    private string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function getName(): string
    {
        return $this->name;
    }
}
