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
 * @psalm-immutable
 */
final class Path extends AbstractCypherContainer
{
    /** @var CypherList<Node> */
    private CypherList $nodes;
    /** @var CypherList<UnboundRelationship> */
    private CypherList $relationships;
    /** @var CypherList<int> */
    private CypherList $ids;

    /**
     * @param CypherList<Node>                $nodes
     * @param CypherList<UnboundRelationship> $relationships
     * @param CypherList<int>                 $ids
     */
    public function __construct(CypherList $nodes, CypherList $relationships, CypherList $ids)
    {
        $this->nodes = $nodes;
        $this->relationships = $relationships;
        $this->ids = $ids;
    }

    /**
     * @return CypherList<Node>
     */
    public function getNodes(): CypherList
    {
        return $this->nodes;
    }

    /**
     * @return CypherList<UnboundRelationship>
     */
    public function getRelationships(): CypherList
    {
        return $this->relationships;
    }

    /**
     * @return CypherList<int>
     */
    public function getIds(): CypherList
    {
        return $this->ids;
    }

    public function getIterator()
    {
        yield 'id' => $this->ids;
        yield 'nodes' => $this->nodes;
        yield 'relationships' => $this->relationships;
    }
}
