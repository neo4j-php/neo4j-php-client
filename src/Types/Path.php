<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Types;

/**
 * A Path class representing a Path in cypher.
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<CypherList<Node>|CypherList<Relationship>|CypherList<int>, CypherList<Node>|CypherList<Relationship>|CypherList<int>>
 */
final class Path extends AbstractPropertyObject
{
    /** @var CypherList<Node> */
    private CypherList $nodes;
    /** @var CypherList<Relationship> */
    private CypherList $relationships;
    /** @var CypherList<int> */
    private CypherList $ids;

    /**
     * @param CypherList<Node>         $nodes
     * @param CypherList<Relationship> $relationships
     * @param CypherList<int>          $ids
     */
    public function __construct(CypherList $nodes, CypherList $relationships, CypherList $ids)
    {
        $this->nodes = $nodes;
        $this->relationships = $relationships;
        $this->ids = $ids;
    }

    /**
     * Returns the node in the path.
     *
     * @return CypherList<Node>
     */
    public function getNodes(): CypherList
    {
        return $this->nodes;
    }

    /**
     * Returns the relationships in the path.
     *
     * @return CypherList<Relationship>
     */
    public function getRelationships(): CypherList
    {
        return $this->relationships;
    }

    /**
     * Returns the ids of the items in the path.
     *
     * @return CypherList<int>
     */
    public function getIds(): CypherList
    {
        return $this->ids;
    }

    /**
     * @return array{id: CypherList<int>, nodes: CypherList<Node>, relationships: CypherList<Relationship>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->ids,
            'nodes' => $this->nodes,
            'relationships' => $this->relationships,
        ];
    }

    public function getProperties(): CypherMap
    {
        return new CypherMap($this);
    }
}
