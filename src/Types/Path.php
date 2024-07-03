<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
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
 * @extends AbstractPropertyObject<CypherList<Node>|CypherList<UnboundRelationship>|CypherList<int>, CypherList<Node>|CypherList<UnboundRelationship>|CypherList<int>>
 */
final class Path extends AbstractPropertyObject
{
    /**
     * @param CypherList<Node>                $nodes
     * @param CypherList<UnboundRelationship> $relationships
     * @param CypherList<int>                 $ids
     */
    public function __construct(
        private readonly CypherList $nodes,
        private readonly CypherList $relationships,
        private readonly CypherList $ids
    ) {}

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
     * @return CypherList<UnboundRelationship>
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
     * @return array{ids: CypherList<int>, nodes: CypherList<Node>, relationships: CypherList<UnboundRelationship>}
     */
    public function toArray(): array
    {
        return [
            'ids' => $this->ids,
            'nodes' => $this->nodes,
            'relationships' => $this->relationships,
        ];
    }

    public function getProperties(): CypherMap
    {
        return new CypherMap($this);
    }
}
