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

final class UnboundRelationship extends AbstractCypherContainer
{
    private int $id;
    private string $type;
    private CypherMap $properties;

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
        return $this->properties;
    }

    public function getIterator()
    {
        yield 'id' => $this->getId();
        yield 'type' => $this->getType();
        yield 'properties' => $this->getProperties();
    }
}
