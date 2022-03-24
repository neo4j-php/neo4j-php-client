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
 * A Relationship class representing a Relationship in cypher.
 *
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @psalm-immutable
 */
final class Relationship extends UnboundRelationship
{
    private int $startNodeId;

    private int $endNodeId;

    /**
     * @param CypherMap<OGMTypes> $properties
     */
    public function __construct(int $id, int $startNodeId, int $endNodeId, string $type, CypherMap $properties)
    {
        parent::__construct($id, $type, $properties);
        $this->startNodeId = $startNodeId;
        $this->endNodeId = $endNodeId;
    }

    /**
     * Returns the id of the start node.
     */
    public function getStartNodeId(): int
    {
        return $this->startNodeId;
    }

    /**
     * Returns the id of the end node.
     */
    public function getEndNodeId(): int
    {
        return $this->endNodeId;
    }

    /**
     * @psalm-suppress ImplementedReturnTypeMismatch False positive.
     *
     * @return array{
     *                id: int,
     *                type: string,
     *                startNodeId: int,
     *                endNodeId: int,
     *                properties: CypherMap<OGMTypes>
     *                }
     */
    public function toArray(): array
    {
        $tbr = parent::toArray();

        $tbr['startNodeId'] = $this->getStartNodeId();
        $tbr['endNodeId'] = $this->getEndNodeId();

        return $tbr;
    }
}
