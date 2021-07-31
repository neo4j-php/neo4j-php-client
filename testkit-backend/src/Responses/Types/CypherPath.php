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

namespace Laudis\Neo4j\TestkitBackend\Responses\Types;

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;

final class CypherPath implements TestkitResponseInterface
{
    private CypherObject $nodes;
    private CypherObject $relationships;

    public function __construct(CypherObject $nodes, CypherObject $relationships)
    {
        $this->nodes = $nodes;
        $this->relationships = $relationships;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'CypherPath',
            'data' => [
                'nodes' => $this->nodes,
                'relationships' => $this->relationships,
            ],
        ];
    }
}
