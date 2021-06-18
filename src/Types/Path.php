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

final class Path extends AbstractCypherContainer
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

    public function getNodes(): CypherList
    {
        return $this->nodes;
    }

    public function getRelationships(): CypherList
    {
        return $this->relationships;
    }

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
