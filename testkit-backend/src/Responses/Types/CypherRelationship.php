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

namespace Laudis\Neo4j\TestkitBackend\Responses\Types;

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;

final class CypherRelationship implements TestkitResponseInterface
{
    private CypherObject $id;
    private CypherObject $startNodeId;
    private CypherObject $endNodeId;
    private CypherObject $type;
    private CypherObject $props;
    private CypherObject $elementId;
    private CypherObject $startNodeElementId;
    private CypherObject $endNodeElementId;

    public function __construct(CypherObject $id, CypherObject $startNodeId, CypherObject $endNodeId, CypherObject $type, CypherObject $props, CypherObject $elementId, CypherObject $startNodeElementId, CypherObject $endNodeElementId)
    {
        $this->id = $id;
        $this->startNodeId = $startNodeId;
        $this->endNodeId = $endNodeId;
        $this->type = $type;
        $this->props = $props;
        $this->elementId = $elementId;
        $this->startNodeElementId = $startNodeElementId;
        $this->endNodeElementId = $endNodeElementId;
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
                'elementId' => $this->elementId,
                'startNodeElementId' => $this->startNodeElementId,
                'endNodeElementId' => $this->endNodeElementId,
            ],
        ];
    }
}
