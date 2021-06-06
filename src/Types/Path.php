<?php

declare(strict_types=1);


namespace Laudis\Neo4j\Types;


final class Path
{
    private CypherList $nodes;
    private CypherList $relationships;
    private CypherList $ids;

    public function __construct(CypherList $nodes, CypherList $relationships, CypherList $ids)
    {
        $this->nodes = $nodes;
        $this->relationships = $relationships;
        $this->ids = $ids;
    }

    /**
     * @return CypherList
     */
    public function getNodes(): CypherList
    {
        return $this->nodes;
    }

    /**
     * @return CypherList
     */
    public function getRelationships(): CypherList
    {
        return $this->relationships;
    }

    /**
     * @return CypherList
     */
    public function getIds(): CypherList
    {
        return $this->ids;
    }
}
