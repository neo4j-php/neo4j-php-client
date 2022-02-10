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

use Laudis\Neo4j\Exception\PropertyDoesNotExistException;
use function sprintf;

/**
 * A Relationship class representing a Relationship in cypher.
 *
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<OGMTypes, int|string|CypherMap<OGMTypes>>
 */
final class Relationship extends AbstractPropertyObject
{
    private int $id;

    private int $startNodeId;

    private int $endNodeId;

    private string $type;

    /** @var CypherMap<OGMTypes> */
    private CypherMap $properties;

    /**
     * @param CypherMap<OGMTypes> $properties
     */
    public function __construct(int $id, int $startNodeId, int $endNodeId, string $type, CypherMap $properties)
    {
        $this->id = $id;
        $this->startNodeId = $startNodeId;
        $this->endNodeId = $endNodeId;
        $this->type = $type;
        $this->properties = $properties;
    }

    /**
     * Returns the id of the relationship.
     */
    public function getId(): int
    {
        return $this->id;
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
     * Returns the type of the relationship.
     */
    public function getType(): string
    {
        return $this->type;
    }

    public function getProperties(): CypherMap
    {
        /** @psalm-suppress InvalidReturnStatement false positive with type alias. */
        return $this->properties;
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
        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'startNodeId' => $this->getStartNodeId(),
            'endNodeId' => $this->getEndNodeId(),
            'properties' => $this->getProperties(),
        ];
    }

    /**
     * Gets the property of the relationship by key.
     *
     * @return OGMTypes
     */
    public function getProperty(string $key)
    {
        /** @psalm-suppress ImpureMethodCall */
        if (!$this->properties->hasKey($key)) {
            throw new PropertyDoesNotExistException(sprintf('Property "%s" does not exist on relationship', $key));
        }

        /** @psalm-suppress ImpureMethodCall */
        return $this->properties->get($key);
    }
}
