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
 * A relationship without any nodes attached to it.
 *
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<OGMTypes, int|string|CypherMap<OGMTypes>>
 */
final class UnboundRelationship extends AbstractPropertyObject
{
    private int $id;
    private string $type;
    /** @var CypherMap<OGMTypes> */
    private CypherMap $properties;

    /**
     * @param CypherMap<OGMTypes> $properties
     */
    public function __construct(int $id, string $type, CypherMap $properties)
    {
        $this->id = $id;
        $this->type = $type;
        $this->properties = $properties;
    }

    public function getId(): int
    {
        return $this->id;
    }

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
     * @return array{id: int, type: string, properties: CypherMap<OGMTypes>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'type' => $this->getType(),
            'properties' => $this->getProperties(),
        ];
    }
}
