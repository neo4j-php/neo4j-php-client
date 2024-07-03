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

use Laudis\Neo4j\Exception\PropertyDoesNotExistException;

use function sprintf;

/**
 * A relationship without any nodes attached to it.
 *
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<OGMTypes, int|string|CypherMap<OGMTypes>>
 */
class UnboundRelationship extends AbstractPropertyObject
{
    /**
     * @param CypherMap<OGMTypes> $properties
     */
    public function __construct(
        private readonly int $id,
        private readonly string $type,
        private readonly CypherMap $properties,
        private readonly ?string $elementId
    ) {}

    public function getElementId(): ?string
    {
        return $this->elementId;
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
