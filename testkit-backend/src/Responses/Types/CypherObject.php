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
use Laudis\Neo4j\Types\Vector;
use RuntimeException;

/**
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 */
final class CypherObject implements TestkitResponseInterface
{
    /** @var CypherList|CypherMap|int|bool|float|string|Node|Relationship|Path|null */
    private $value;
    private string $name;

    // Store element ID mappings for relationships created from paths
    private static array $relationshipElementIdMap = [];

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

    /**
     * @param OGMTypes $value
     */
    public static function autoDetect($value): TestkitResponseInterface
    {
        switch (get_debug_type($value)) {
            case 'null':
                $tbr = new CypherObject('CypherNull', $value);
                break;
            case CypherList::class:
                /** @var CypherList<OGMTypes> $value */
                $list = [];
                foreach ($value as $item) {
                    $list[] = self::autoDetect($item);
                }

                $tbr = new CypherObject('CypherList', new CypherList($list));
                break;
            case CypherMap::class:
                /** @var CypherMap<OGMTypes> $value */
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
            case Vector::class:
                /** @var Vector $value */
                $list = [];
                foreach ($value->getValues() as $item) {
                    $list[] = self::autoDetect($item);
                }
                $tbr = new CypherObject('Vector', new CypherList($list));
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
                    /** @psalm-suppress MixedArgumentTypeCoercion */
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
                    /** @psalm-suppress MixedArgumentTypeCoercion */
                    $props[$key] = self::autoDetect($property);
                }

                $elementId = $value->getElementId();
                if ($elementId === null) {
                    $elementId = (string) $value->getId();
                }

                // First check if the relationship has methods to get start/end node element IDs
                $startNodeElementId = null;
                $endNodeElementId = null;

                if (method_exists($value, 'getStartNodeElementId')) {
                    $startNodeElementId = $value->getStartNodeElementId();
                }
                if (method_exists($value, 'getEndNodeElementId')) {
                    $endNodeElementId = $value->getEndNodeElementId();
                }

                // If not available directly, check our stored mappings from paths
                if ($startNodeElementId === null || $endNodeElementId === null) {
                    $relationshipKey = $value->getId().'_'.$value->getStartNodeId().'_'.$value->getEndNodeId();

                    if ($startNodeElementId === null) {
                        $startNodeElementId = self::$relationshipElementIdMap[$relationshipKey]['startNodeElementId'] ?? (string) $value->getStartNodeId();
                    }
                    if ($endNodeElementId === null) {
                        $endNodeElementId = self::$relationshipElementIdMap[$relationshipKey]['endNodeElementId'] ?? (string) $value->getEndNodeId();
                    }
                }

                $tbr = new CypherRelationship(
                    new CypherObject('CypherInt', $value->getId()),
                    new CypherObject('CypherInt', $value->getStartNodeId()),
                    new CypherObject('CypherInt', $value->getEndNodeId()),
                    new CypherObject('CypherString', $value->getType()),
                    new CypherObject('CypherMap', new CypherMap($props)),
                    new CypherObject('CypherString', $elementId),
                    new CypherObject('CypherString', $startNodeElementId), // Use stored element ID
                    new CypherObject('CypherString', $endNodeElementId)    // Use stored element ID
                );
                break;
            case Path::class:
                $nodes = [];
                foreach ($value->getNodes() as $node) {
                    $nodes[] = self::autoDetect($node);
                }

                $rels = [];
                $nodesList = $value->getNodes();

                foreach ($value->getRelationships() as $i => $rel) {
                    $relElementId = $rel->getElementId() ?? (string) $rel->getId();

                    if ($rel instanceof UnboundRelationship) {
                        if ($i < $nodesList->count() - 1) {
                            $startNode = $nodesList->get($i);
                            $endNode = $nodesList->get($i + 1);

                            $startNodeElementId = $startNode->getElementId() ?? (string) $startNode->getId();
                            $endNodeElementId = $endNode->getElementId() ?? (string) $endNode->getId();

                            $boundRel = new Relationship(
                                $rel->getId(),
                                $startNode->getId(),
                                $endNode->getId(),
                                $rel->getType(),
                                $rel->getProperties(),
                                $relElementId
                            );

                            $relationshipKey = $boundRel->getId().'_'.$boundRel->getStartNodeId().'_'.$boundRel->getEndNodeId();
                            self::$relationshipElementIdMap[$relationshipKey] = [
                                'startNodeElementId' => $startNodeElementId,
                                'endNodeElementId' => $endNodeElementId,
                            ];
                            $rels[] = self::autoDetect($boundRel);
                        }
                    } else {
                        $rels[] = self::autoDetect($rel);
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
                    /** @psalm-suppress MixedArgumentTypeCoercion */
                    $props[$key] = self::autoDetect($property);
                }

                $tbr = new CypherRelationship(
                    new CypherObject('CypherInt', $value->getId()),
                    new CypherObject('CypherNull', null),
                    new CypherObject('CypherNull', null),
                    new CypherObject('CypherString', $value->getType()),
                    new CypherObject('CypherMap', new CypherMap($props)),
                    new CypherObject('CypherString', $value->getElementId()),
                    new CypherObject('CypherNull', null),
                    new CypherObject('CypherNull', null)
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
