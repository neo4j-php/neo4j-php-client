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

namespace Laudis\Neo4j\TestkitBackend\Responses\Types;

use function get_debug_type;

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Path;
use Laudis\Neo4j\Types\Relationship;
use Laudis\Neo4j\Types\UnboundRelationship;
use RuntimeException;

final class CypherObject implements TestkitResponseInterface
{
    /** @var CypherList|CypherMap|int|bool|float|string|Node|Relationship|Path|null */
    private $value;
    private string $name;

    /**
     * @param CypherList|CypherMap|int|bool|float|string|Node|Relationship|Path|null $value
     */
    public function __construct(string $name, $value)
    {
        $this->value = $value;
        $this->name = $name;
    }

    /**
     * @return bool|float|int|CypherList|CypherMap|Node|Path|Relationship|string|null
     */
    public function getValue()
    {
        return $this->value;
    }

    public static function autoDetect($value): TestkitResponseInterface
    {
        switch (get_debug_type($value)) {
            case 'null':
                $tbr = new CypherObject('CypherNull', $value);
                break;
            case CypherList::class:
                $list = [];
                foreach ($value as $item) {
                    $list[] = self::autoDetect($item);
                }
                $tbr = new CypherObject('CypherList', new CypherList($list));
                break;
            case CypherMap::class:
                if ($value->count() === 2 && $value->hasKey('name') && $value->hasKey('data')) {
                    $tbr = new CypherObject('CypherMap', $value);
                } else {
                    $map = [];
                    foreach ($value as $key => $item) {
                        $map[$key] = self::autoDetect($item);
                    }
                    $tbr = new CypherObject('CypherMap', new CypherMap($map));
                }
                break;
            case 'int':
                $tbr = new CypherObject('CypherInt', $value);
                break;
            case 'bool':
                $tbr = new CypherObject('CypherBool', $value);
                break;
            case 'float':
                $tbr = new CypherObject('CypherFloat', $value);
                break;
            case 'string':
                $tbr = new CypherObject('CypherString', $value);
                break;
            case Node::class:
                $labels = [];
                foreach ($value->getLabels() as $label) {
                    $labels[] = self::autoDetect($label);
                }
                $props = [];
                foreach ($value->getProperties() as $key => $property) {
                    $props[$key] = self::autoDetect($property);
                }
                $elementId = $value->getElementId();
                if ($elementId === null) {
                    $elementId = (string) $value->getId();
                }
                $tbr = new CypherNode(
                    new CypherObject('CypherInt', $value->getId()),
                    new CypherObject('CypherList', new CypherList($labels)),
                    new CypherObject('CypherMap', new CypherMap($props)),
                    new CypherObject('CypherString', $elementId)
                );
                break;
            case Relationship::class:
                $props = [];
                foreach ($value->getProperties() as $key => $property) {
                    $props[$key] = self::autoDetect($property);
                }
                $elementId = $value->getElementId();
                if ($elementId === null) {
                    $elementId = (string) $value->getId();
                }
                $startNodeElementId = null;
                $endNodeElementId = null;
                if (method_exists($value, 'getStartNodeElementId')) {
                    $startNodeElementId = $value->getStartNodeElementId();
                }
                if ($startNodeElementId === null) {
                    $startNodeElementId = (string) $value->getStartNodeId();
                }
                if (method_exists($value, 'getEndNodeElementId')) {
                    $endNodeElementId = $value->getEndNodeElementId();
                }
                if ($endNodeElementId === null) {
                    $endNodeElementId = (string) $value->getEndNodeId();
                }
                $tbr = new CypherRelationship(
                    new CypherObject('CypherInt', $value->getId()),
                    new CypherObject('CypherInt', $value->getStartNodeId()),
                    new CypherObject('CypherInt', $value->getEndNodeId()),
                    new CypherObject('CypherString', $value->getType()),
                    new CypherObject('CypherMap', new CypherMap($props)),
                    new CypherObject('CypherString', $elementId),
                    new CypherObject('CypherString', $startNodeElementId),
                    new CypherObject('CypherString', $endNodeElementId)
                );
                break;
            case Path::class:
                $nodes = [];
                foreach ($value->getNodes() as $node) {
                    $nodeElementId = $node->getElementId();
                    if ($nodeElementId === null) {
                        $nodeElementId = (string) $node->getId();
                    }

                    $nodes[] = new CypherNode(
                        new CypherObject('CypherInt', $node->getId()),
                        self::autoDetect($node->getLabels()),
                        self::autoDetect($node->getProperties()),
                        new CypherObject('CypherString', $nodeElementId)
                    );
                }

                $nodeList = $value->getNodes();
                $relationshipList = $value->getRelationships();
                $nodeCount = count($nodeList);

                $rels = [];
                foreach ($relationshipList as $i => $rel) {
                    if ($i + 1 >= $nodeCount) {
                        break;
                    }

                    $startNode = $nodeList->get($i);
                    $endNode = $nodeList->get($i + 1);

                    if ($startNode !== null && $endNode !== null) {
                        $startNodeId = $startNode->getId();
                        $endNodeId = $endNode->getId();

                        $startNodeElementId = $startNode->getElementId();
                        if ($startNodeElementId === null) {
                            $startNodeElementId = (string) $startNodeId;
                        }

                        $endNodeElementId = $endNode->getElementId();
                        if ($endNodeElementId === null) {
                            $endNodeElementId = (string) $endNodeId;
                        }

                        $relElementId = $rel->getElementId();
                        if ($relElementId === null) {
                            $relElementId = (string) $rel->getId();
                        }

                        $rels[] = new CypherRelationship(
                            new CypherObject('CypherInt', $rel->getId()),
                            new CypherObject('CypherInt', $startNodeId),
                            new CypherObject('CypherInt', $endNodeId),
                            new CypherObject('CypherString', $rel->getType()),
                            new CypherObject('CypherMap', new CypherMap($rel->getProperties())),
                            new CypherObject('CypherString', $relElementId),
                            new CypherObject('CypherString', $startNodeElementId),
                            new CypherObject('CypherString', $endNodeElementId)
                        );
                    }
                }

                $tbr = new CypherPath(
                    new CypherObject('CypherList', new CypherList($nodes)),
                    new CypherObject('CypherList', new CypherList($rels))
                );
                break;

            case UnboundRelationship::class:
                $props = [];
                foreach ($value->getProperties() as $key => $property) {
                    $props[$key] = self::autoDetect($property);
                }
                $elementId = $value->getElementId();
                if ($elementId === null) {
                    $elementId = (string) $value->getId();
                }
                $tbr = new CypherRelationship(
                    new CypherObject('CypherInt', $value->getId()),
                    new CypherObject('CypherInt', $value->getId()),
                    new CypherObject('CypherInt', $value->getId()),
                    new CypherObject('CypherString', $value->getType()),
                    new CypherObject('CypherMap', new CypherMap($props)),
                    new CypherObject('CypherString', $elementId),
                    new CypherObject('CypherString', $elementId),
                    new CypherObject('CypherString', $elementId)
                );
                break;
            default:
                throw new RuntimeException('Unexpected type: '.get_debug_type($value));
        }

        return $tbr;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => $this->name,
            'data' => [
                'value' => $this->value,
            ],
        ];
    }
}
