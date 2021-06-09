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

use Ds\Map;
use Ds\Vector;
use function is_array;
use Iterator;
use Laudis\Neo4j\Contracts\PointInterface;
use Laudis\Neo4j\Types\Cartesian3DPoint;
use Laudis\Neo4j\Types\CartesianPoint;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Relationship;
use Laudis\Neo4j\Types\WGS843DPoint;
use Laudis\Neo4j\Types\WGS84Point;

/**
 * @psalm-type RelationshipArray = array{id: string, type: string, startNode: string, endNode: string, properties?: array<string, scalar|null|array<array-key, scalar|null|array>>}
 * @psalm-type NodeArray = array{id: string, labels: list<string>, properties: array<string, scalar|null|array}
 * @psalm-type MetaArray = null|array{id?: int, type: string, deleted?: bool}
 *
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 */
final class HttpOGMArrayTranslator
{
    /**
     * @param Iterator<RelationshipArray> $relationship
     */
    private function relationship(Iterator $relationship): Relationship
    {
        $rel = $relationship->current();
        $relationship->next();
        /** @var Map<string, OGMTypes> $map */
        $map = new Map();
        foreach ($rel['properties'] ?? [] as $key => $x) {
            // We only need to recurse over array types.
            // Nested types gets erased in the legacy http api.
            // We need to use JOLT instead for finer control,
            // which will be a different translator.
            if (is_array($x)) {
                $map->put($key, $this->translateContainer($x));
            } else {
                $map->put($key, $x);
            }
        }

        return new Relationship(
            (int) $rel['id'],
            (int) $rel['startNode'],
            (int) $rel['endNode'],
            $rel['type'],
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
        /** @var Vector<OGMTypes> $tbr */
        $tbr = new Vector();
        foreach ($value as $x) {
            // We only need to recurse over array types.
            // Nested types gets erased in the legacy http api.
            // We need to use JOLT instead for finer control,
            // which will be a different translator.
            if (is_array($x)) {
                $tbr->push($this->translateContainer($value));
            } else {
                $tbr->push($x);
            }
        }

        return new CypherList($tbr);
    }

    /**
     * @param Iterator<RelationshipArray> $relationship
     * @param Iterator<MetaArray>         $meta
     * @param list<NodeArray>             $nodes
     *
     * @return Cartesian3DPoint|CartesianPoint|CypherList|CypherMap|Node|Relationship|WGS843DPoint|WGS84Point
     */
    public function translate(Iterator $meta, Iterator $relationship, array $nodes, array $value): object
    {
        $currentMeta = $meta->current();
        $meta->next();
        $type = $currentMeta['type'] ?? null;

        switch ($type) {
            case 'relationship':
                $tbr = $this->relationship($relationship);
                break;
            case 'point':
                $tbr = $this->translatePoint($value);
                break;
            default:
                /** @var array<array-key, array|null|scalar> $value */
                $tbr = $this->translateContainer($value);
                if ($type === 'node' && $tbr instanceof CypherMap && isset($currentMeta['id'])) {
                    $tbr = $this->translateNode($nodes, $currentMeta['id'], $tbr);
                }
                break;
        }

        return $tbr;
    }

    /**
     * @param list<NodeArray>     $nodes
     * @param CypherMap<OGMTypes> $tbr
     */
    private function translateNode(array $nodes, int $id, CypherMap $tbr): Node
    {
        /** @var list<string> $labels */
        $labels = [];
        foreach ($nodes as $node) {
            if ((int) $node['id'] === $id) {
                $labels = $node['labels'];
                break;
            }
        }

        return new Node($id, new CypherList(new Vector($labels)), $tbr);
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
        /** @var Map<string, OGMTypes> $tbr */
        $tbr = new Map();
        foreach ($value as $key => $x) {
            // We only need to recurse over array types.
            // Nested types gets erased in the legacy http api.
            // We need to use JOLT instead for finer control,
            // which will be a different translator.
            if (is_array($x)) {
                /** @var array<array-key, scalar|null|array> $x */
                $tbr->put($key, $this->translateContainer($x));
            } else {
                $tbr->put($key, $x);
            }
        }

        return new CypherMap($tbr);
    }

    /**
     * @param array<array-key, scalar|null|array> $value
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
