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
    private string $startNodeElementId;
    private string $endNodeElementId;

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
        int|string|null $startNodeElementId = null,
        int|string|null $endNodeElementId = null,
    ) {
        parent::__construct($id, $type, $properties, $elementId);
        $this->startNodeElementId = $startNodeElementId !== null
            ? (string) $startNodeElementId
            : (string) $startNodeId;

        $this->endNodeElementId = $endNodeElementId !== null
            ? (string) $endNodeElementId
            : (string) $endNodeId;
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
