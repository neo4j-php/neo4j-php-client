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
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 */
final class Relationship extends AbstractCypherContainer
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

    public function getId(): int
    {
        return $this->id;
    }

    public function getStartNodeId(): int
    {
        return $this->startNodeId;
    }

    public function getEndNodeId(): int
    {
        return $this->endNodeId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return CypherMap<OGMTypes>
     */
    public function getProperties(): CypherMap
    {
        return $this->properties;
    }

    public function getIterator()
    {
        yield 'id' => $this->getId();
        yield 'type' => $this->getType();
        yield 'startNodeId' => $this->getStartNodeId();
        yield 'endNodeId' => $this->getEndNodeId();
        yield 'properties' => $this->getProperties();
    }
}
