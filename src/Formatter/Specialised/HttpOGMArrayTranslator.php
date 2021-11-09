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

namespace Laudis\Neo4j\Formatter\Specialised;

use function is_array;
use Laudis\Neo4j\Contracts\PointInterface;
use Laudis\Neo4j\Types\Cartesian3DPoint;
use Laudis\Neo4j\Types\CartesianPoint;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Path;
use Laudis\Neo4j\Types\Relationship;
use Laudis\Neo4j\Types\UnboundRelationship;
use Laudis\Neo4j\Types\WGS843DPoint;
use Laudis\Neo4j\Types\WGS84Point;
use RuntimeException;

/**
 * Maps the arrays to their respective values.
 *
 * @psalm-type RelationshipArray = array{id: string, type: string, startNode: string, endNode: string, properties: array<string, scalar|null|array<array-key, scalar|null|array>>}
 * @psalm-type NodeArray = array{id: string, labels: list<string>, properties: array<string, scalar|null|array}
 * @psalm-type Meta = array{id?: int, type: string, deleted?: bool}
 * @psalm-type MetaArray = list<Meta|null|list<array{id: int, type: string, deleted: bool}>>
 *
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @psalm-immutable
 */
final class HttpOGMArrayTranslator
{
    /**
     * @param RelationshipArray $relationship
     */
    private function relationship(array $relationship): Relationship
    {
        /** @var array<string, OGMTypes> $map */
        $map = [];
        foreach ($relationship['properties'] ?? [] as $key => $x) {
            // We only need to recurse over array types.
            // Nested types gets erased in the legacy http api.
            // We need to use JOLT instead for finer control,
            // which will be a different translator.
            if (is_array($x)) {
                $map[$key] = $this->translateContainer($x);
            } else {
                $map[$key] = $x;
            }
        }

        return new Relationship(
            (int) $relationship['id'],
            (int) $relationship['startNode'],
            (int) $relationship['endNode'],
            $relationship['type'],
            new CypherMap($map)
        );
    }

    /**
     * @param list<scalar|array|null> $value
     *
     * @return CypherList<OGMTypes>
     */
    private function translateCypherList(array $value): CypherList
    {
        /** @var array<OGMTypes> $tbr */
        $tbr = [];
        foreach ($value as $x) {
            // We only need to recurse over array types.
            // Nested types gets erased in the legacy http api.
            // We need to use JOLT instead for finer control,
            // which will be a different translator.
            if (is_array($x)) {
                /** @var array<array-key, array|scalar|null> $x */
                $tbr[] = $this->translateContainer($x);
            } else {
                $tbr[] = $x;
            }
        }

        return new CypherList($tbr);
    }

    /**
     * @param list<RelationshipArray> $relationships
     * @param MetaArray               $meta
     * @param list<NodeArray>         $nodes
     *
     * @return array{0: int, 1: int, 2:Cartesian3DPoint|CartesianPoint|CypherList|CypherMap|Node|Relationship|WGS843DPoint|WGS84Point|Path}
     */
    public function translate(array $meta, array $relationships, int $metaIndex, int $relationshipIndex, array $nodes, array $value): array
    {
        $currentMeta = $meta[$metaIndex];
        $metaIncrease = 1;
        $relationshipIncrease = 0;
        $type = $currentMeta === null ? null : ($currentMeta['type'] ?? 'path');

        switch ($type) {
            case 'relationship':
                $tbr = $this->relationship($relationships[$relationshipIndex]);
                ++$relationshipIncrease;
                break;
            case 'path':
                /**
                 * @psalm-suppress UnnecessaryVarAnnotation False positive
                 *
                 * @var list<array{id: int, type: string, deleted: bool}> $currentMeta
                 */
                [$path, $relIncrease] = $this->path($currentMeta, $nodes, $relationships, $relationshipIndex);
                $relationshipIncrease += $relIncrease;
                $tbr = $path;
                break;
            case 'point':
                $tbr = $this->translatePoint($value);
                break;
            default:
                if ($type === 'node' && isset($currentMeta['id'])) {
                    /** @var int $id */
                    $id = $currentMeta['id'];
                    $tbr = $this->translateNode($nodes, $id);
                } else {
                    /** @var array<array-key, array|scalar|null> $value */
                    $tbr = $this->translateContainer($value);
                }
                break;
        }

        return [$metaIncrease, $relationshipIncrease, $tbr];
    }

    /**
     * @param list<array{id: int, type: string, deleted: bool}> $meta
     * @param list<NodeArray>                                   $nodes
     * @param list<RelationshipArray>                           $relationships
     *
     * @return array{0: Path, 1: int}
     */
    private function path(array $meta, array $nodes, array $relationships, int $relIndex): array
    {
        $nodesTbr = [];
        /** @var list<int> $ids */
        $ids = [];
        $rels = [];
        $relIncrease = 0;
        foreach ($meta as $nodeOrRel) {
            if ($nodeOrRel['type'] === 'relationship') {
                $rel = $relationships[$relIndex];
                ++$relIndex;
                ++$relIncrease;
                $props = $this->translateCypherMap($rel['properties']);
                $rels[] = new UnboundRelationship((int) $rel['id'], $rel['type'], $props);
            } else {
                $nodesTbr[] = $this->translateNode($nodes, $nodeOrRel['id']);
            }
            $ids[] = $nodeOrRel['id'];
        }

        return [new Path(new CypherList($nodesTbr), new CypherList($rels), new CypherList($ids)), $relIncrease];
    }

    /**
     * @param list<NodeArray> $nodes
     */
    private function translateNode(array $nodes, int $id): Node
    {
        foreach ($nodes as $node) {
            if ((int) $node['id'] === $id) {
                return new Node($id, new CypherList($node['labels']), $this->translateCypherMap($node['properties']));
            }
        }

        throw new RuntimeException('Error when translating node: Cannot find node with id: '.$id);
    }

    /**
     * @return CartesianPoint|Cartesian3DPoint|WGS843DPoint|WGS84Point
     */
    private function translatePoint(array $value): PointInterface
    {
        /** @var array{type: 'point', coordinates: array{0: float, 1: float, 2?:float}, crs: array{srid: int, type: string, name: 'cartesian'|'cartesian-3d'|'wgs-84'|'wgs-84-3d', properties: array<string, string>}} $value */
        $pointType = $value['crs']['name'];
        if ($pointType === 'cartesian') {
            return new CartesianPoint(
                $value['coordinates'][0],
                $value['coordinates'][1],
                $value['crs']['name'],
                $value['crs']['srid']
            );
        }
        if ($pointType === 'cartesian-3d') {
            return new Cartesian3DPoint(
                $value['coordinates'][0],
                $value['coordinates'][1],
                $value['coordinates'][2] ?? 0.0,
                $value['crs']['name'],
                $value['crs']['srid']
            );
        }
        if ($pointType === 'wgs-84') {
            return new WGS84Point(
                $value['coordinates'][0],
                $value['coordinates'][1],
                $value['coordinates'][0],
                $value['coordinates'][1],
                $value['crs']['name'],
                $value['crs']['srid']
            );
        }

        return new WGS843DPoint(
            $value['coordinates'][0],
            $value['coordinates'][1],
            $value['coordinates'][2] ?? 0.0,
            $value['coordinates'][0],
            $value['coordinates'][1],
            $value['coordinates'][2] ?? 0.0,
            $value['crs']['name'],
            $value['crs']['srid']
        );
    }

    /**
     * @param array<string, scalar|array|null> $value
     *
     * @return CypherMap<OGMTypes>
     */
    private function translateCypherMap(array $value): CypherMap
    {
        /** @var array<string, OGMTypes> $tbr */
        $tbr = [];
        foreach ($value as $key => $x) {
            // We only need to recurse over array types.
            // Nested types gets erased in the legacy http api.
            // We need to use JOLT instead for finer control,
            // which will be a different translator.
            if (is_array($x)) {
                /** @var array<array-key, scalar|array|null> $x */
                $tbr[$key] = $this->translateContainer($x);
            } else {
                $tbr[$key] = $x;
            }
        }

        return new CypherMap($tbr);
    }

    /**
     * @param array<array-key, scalar|array|null> $value
     *
     * @return CypherList<OGMTypes>|CypherMap<OGMTypes>
     */
    private function translateContainer(array $value)
    {
        if (isset($value[0])) {
            /** @var list<scalar|array|null> $value */
            return $this->translateCypherList($value);
        }

        /** @var array<string, scalar|array|null> $value */
        return $this->translateCypherMap($value);
    }
}
