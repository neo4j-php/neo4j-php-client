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

final class HttpOGMArrayTranslator
{
    private function relationship(Iterator $relationship): Relationship
    {
        $rel = $relationship->current();
        $relationship->next();
        /** @var Map<string, array|scalar|null> $map */
        $map = new Map();
        foreach ($rel['properties'] ?? [] as $key => $x) {
            $map->put($key, $x);
        }

        return new Relationship(
            (int) $rel['id'],
            (int) $rel['startNode'],
            (int) $rel['endNode'],
            $rel['type'],
            new CypherMap($map)
        );
    }

    private function translateCypherList(array $value): CypherList
    {
        $tbr = new Vector();
        foreach ($value as $x) {
            $tbr->push($x);
        }

        return new CypherList($tbr);
    }

    /**
     * @return Cartesian3DPoint|CartesianPoint|CypherList|CypherMap|Node|Relationship|WGS843DPoint|WGS84Point
     */
    public function translate(Iterator $meta, Iterator $relationship, array $nodes, array $value)
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
                if (isset($value[0])) {
                    $tbr = $this->translateCypherList($value);
                    break;
                }
                $tbr = $this->translateCypherMap($value);
                if ($type === 'node') {
                    $tbr = $this->translateNode($nodes, $currentMeta['id'], $tbr);
                }
                break;
        }

        return $tbr;
    }

    private function translateNode(array $nodes, int $id, CypherMap $tbr): Node
    {
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
        /** @psalm-suppress PossiblyUndefinedStringArrayOffset */
        $pointType = $value['crs']['name'];
        if ($pointType === 'cartesian') {
            /** @psalm-suppress PossiblyUndefinedStringArrayOffset */
            return new CartesianPoint(
                $value['coordinates'][0],
                $value['coordinates'][1],
                $value['crs']['name'],
                $value['crs']['srid']
            );
        }
        if ($pointType === 'cartesian-3d') {
            /** @psalm-suppress PossiblyUndefinedStringArrayOffset */
            return new Cartesian3DPoint(
                $value['coordinates'][0],
                $value['coordinates'][1],
                $value['coordinates'][2],
                $value['crs']['name'],
                $value['crs']['srid']
            );
        }
        if ($pointType === 'wgs-84') {
            /** @psalm-suppress PossiblyUndefinedStringArrayOffset */
            return new WGS84Point(
                $value['coordinates'][0],
                $value['coordinates'][1],
                $value['coordinates'][0],
                $value['coordinates'][1],
                $value['crs']['name'],
                $value['crs']['srid']
            );
        }

        /** @psalm-suppress PossiblyUndefinedStringArrayOffset */
        return new WGS843DPoint(
            $value['coordinates'][0],
            $value['coordinates'][1],
            $value['coordinates'][2],
            $value['coordinates'][0],
            $value['coordinates'][1],
            $value['coordinates'][2],
            $value['crs']['name'],
            $value['crs']['srid']
        );
    }

    /**
     * @return CypherMap<scalar|array|null>
     */
    private function translateCypherMap(array $value): CypherMap
    {
        /** @var Map<string, scalar|array|null> $tbr */
        $tbr = new Map();
        foreach ($value as $key => $x) {
            $tbr->put($key, $x);
        }

        return new CypherMap($tbr);
    }
}
