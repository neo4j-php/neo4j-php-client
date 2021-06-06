<?php
declare(strict_types=1);


namespace Laudis\Neo4j\Types;


final class UnboundRelationship
{
    private int $id;
    private string $type;
    private CypherMap $properties;

    public function __construct(int $id, string $type, CypherMap $properties)
    {
        $this->id = $id;
        $this->type = $type;
        $this->properties = $properties;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return CypherMap
     */
    public function getProperties(): CypherMap
    {
        return $this->properties;
    }
}
