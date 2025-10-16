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

use Laudis\Neo4j\Formatter\SummarizedResultFormatter;

/**
 * A Relationship class representing a Relationship in cypher.
 *
 * @psalm-import-type OGMTypes from SummarizedResultFormatter
 *
 * @psalm-immutable
 */
final class Relationship extends UnboundRelationship
{
    /**
     * @param CypherMap<OGMTypes> $properties
     */
    public function __construct(
        int $id,
        private readonly int $startNodeId,
        private readonly int $endNodeId,
        string $type,
        CypherMap $properties,
        ?string $elementId,
        private readonly ?string $startNodeElementId = null,
        private readonly ?string $endNodeElementId = null,
    ) {
        parent::__construct($id, $type, $properties, $elementId);
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
     * Returns the element ID of the start node.
     */
    public function getStartNodeElementId(): ?string
    {
        return $this->startNodeElementId;
    }

    /**
     * Returns the element ID of the end node.
     */
    public function getEndNodeElementId(): ?string
    {
        return $this->endNodeElementId;
    }

    /**
     * @psalm-suppress ImplementedReturnTypeMismatch False positive.
     *
     * @return array{
     *                id: int,
     *                type: string,
     *                startNodeId: int,
     *                endNodeId: int,
     *                properties: CypherMap<OGMTypes>,
     *                startNodeElementId: ?string,
     *                endNodeElementId: ?string
     *                }
     */
    public function toArray(): array
    {
        $tbr = parent::toArray();

        $tbr['startNodeId'] = $this->getStartNodeId();
        $tbr['endNodeId'] = $this->getEndNodeId();
        $tbr['startNodeElementId'] = $this->getStartNodeElementId();
        $tbr['endNodeElementId'] = $this->getEndNodeElementId();

        return $tbr;
    }
}
