<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Formatter\Specialised;

use function is_array;
use stdClass;

/**
 * @psalm-type RelationshipArray = array{id: string, type: string, startNode: string, endNode: string, properties: array<string, scalar|null|array<array-key, scalar|null|array>>}
 * @psalm-type NodeArray = array{id: string, labels: list<string>, properties: array<string, scalar|null|array}
 * @psalm-type Meta = array{id?: int, type: string, deleted?: bool}
 * @psalm-type MetaArray = list<Meta|null|list<array{id: int, type: string, deleted: bool}>>
 * @psalm-type CypherResultDataRow = array{row: list<scalar|array|null>, meta: MetaArray, graph: array{nodes: list<NodeArray>, relationships: list<RelationshipArray>}}
 *
 * @psalm-immutable
 */
final class HttpMetaInfo
{
    /** @var list<stdClass|list> */
    private array $meta;
    /** @var list<stdClass> */
    private array $nodes;
    /** @var list<stdClass> */
    private array $relationships;
    private int $currentMeta;

    /**
     * @param list<stdClass> $relationships
     * @param list<stdClass> $meta
     * @param list<stdClass> $nodes
     */
    public function __construct(
        array $meta,
        array $nodes,
        array $relationships,
        int $currentMeta = 0
    ) {
        $this->meta = $meta;
        $this->nodes = $nodes;
        $this->relationships = $relationships;
        $this->currentMeta = $currentMeta;
    }

    /**
     * @pure
     */
    public static function createFromData(stdClass $data): self
    {
        /** @var stdClass */
        $graph = $data->graph;

        return new self($data->meta, $graph->nodes, $graph->relationships);
    }

    /**
     * @return stdClass|list|null
     */
    public function currentMeta()
    {
        return $this->meta[$this->currentMeta] ?? null;
    }

    public function currentNode(): ?stdClass
    {
        $meta = $this->currentMeta();
        if ($meta === null || is_array($meta)) {
            return null;
        }

        foreach ($this->nodes as $node) {
            if ((int) $node->id === $meta->id) {
                return $node;
            }
        }

        return null;
    }

    public function getCurrentRelationship(): ?stdClass
    {
        $meta = $this->currentMeta();
        if ($meta === null || is_array($meta)) {
            return null;
        }

        foreach ($this->relationships as $relationship) {
            if ((int) $relationship->id === $meta->id) {
                return $relationship;
            }
        }

        return null;
    }

    public function getCurrentType(): ?string
    {
        $currentMeta = $this->currentMeta();
        if (is_array($currentMeta)) {
            return 'path';
        }

        if ($currentMeta === null) {
            return null;
        }

        return $currentMeta->type;
    }

    public function withNestedMeta(): self
    {
        $tbr = clone $this;

        $tbr->meta = (array) $this->currentMeta();
        $tbr->currentMeta = 0;

        return $tbr;
    }

    public function incrementMeta(): self
    {
        $tbr = clone $this;
        ++$tbr->currentMeta;

        return $tbr;
    }
}
