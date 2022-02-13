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
 * A Node class representing a Node in cypher.
 *
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @psalm-immutable
 *
 * @psalm-immutable
 *
 * @extends AbstractPropertyObject<OGMTypes, int|string|CypherMap<OGMTypes>>
 * @extends AbstractPropertyObject<OGMTypes, int|CypherList<string>|CypherMap<OGMTypes>>
 */
final class Node extends AbstractPropertyObject
{
    private int $id;
    /** @var CypherList<string> */
    private CypherList $labels;
    /** @var CypherMap<OGMTypes> */
    private CypherMap $properties;

    /**
     * @param CypherList<string>  $labels
     * @param CypherMap<OGMTypes> $properties
     */
    public function __construct(int $id, CypherList $labels, CypherMap $properties)
    {
        $this->id = $id;
        $this->labels = $labels;
        $this->properties = $properties;
    }

    /**
     * @deprecated
     * @see self::getLabels
     *
     * @return CypherList<string>
     */
    public function labels(): CypherList
    {
        return $this->labels;
    }

    /**
     * The labels on the node.
     *
     * @return CypherList<string>
     */
    public function getLabels(): CypherList
    {
        return $this->labels;
    }

    /**
     * @return CypherMap<OGMTypes>
     *
     * @deprecated
     * @see self::getProperties
     */
    public function properties(): CypherMap
    {
        return $this->properties;
    }

    /**
     * @deprecated
     * @see self::getId
     */
    public function id(): int
    {
        return $this->id;
    }

    /**
     * The id of the node.
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Gets the property of the node by key.
     *
     * @return OGMTypes
     */
    public function getProperty(string $key)
    {
        /** @psalm-suppress ImpureMethodCall */
        if (!$this->properties->hasKey($key)) {
            throw new PropertyDoesNotExistException(sprintf('Property "%s" does not exist on node', $key));
        }

        /** @psalm-suppress ImpureMethodCall */
        return $this->properties->get($key);
    }

    /**
     * @psalm-suppress ImplementedReturnTypeMismatch False positive.
     *
     * @return array{id: int, labels: CypherList<string>, properties: CypherMap<OGMTypes>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'labels' => $this->labels,
            'properties' => $this->properties,
        ];
    }

    public function getProperties(): CypherMap
    {
        /** @psalm-suppress InvalidReturnStatement false positive with type alias. */
        return $this->properties;
    }
}
