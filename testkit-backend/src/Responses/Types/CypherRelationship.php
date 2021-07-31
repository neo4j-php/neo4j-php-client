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

final class CypherRelationship implements TestkitResponseInterface
{
    private int $id;
    private int $startNodeId;
    private int $endNodeId;
    private string $type;
    private iterable $props;

    public function __construct(int $id, int $startNodeId, int $endNodeId, string $type, iterable $props)
    {
        $this->id = $id;
        $this->startNodeId = $startNodeId;
        $this->endNodeId = $endNodeId;
        $this->type = $type;
        $this->props = $props;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'CypherRelationship',
            'data' => [
                'id' => $this->id,
                'startNodeId' => $this->startNodeId,
                'endNodeId' => $this->endNodeId,
                'type' => $this->type,
                'props' => $this->props,
            ],
        ];
    }
}
