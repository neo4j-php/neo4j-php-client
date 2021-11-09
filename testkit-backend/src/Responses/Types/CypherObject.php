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

/**
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 */
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

                $tbr = new CypherNode(
                    new CypherObject('CypherInt', $value->getId()),
                    new CypherObject('CypherList', new CypherList($labels)),
                    new CypherObject('CypherMap', new CypherMap($props))
                );
                break;
            case Relationship::class:
                $props = [];
                foreach ($value->getProperties() as $key => $property) {
                    /** @psalm-suppress MixedArgumentTypeCoercion */
                    $props[$key] = self::autoDetect($property);
                }

                $tbr = new CypherRelationship(
                    new CypherObject('CypherInt', $value->getId()),
                    new CypherObject('CypherInt', $value->getStartNodeId()),
                    new CypherObject('CypherInt', $value->getEndNodeId()),
                    new CypherObject('CypherString', $value->getType()),
                    new CypherObject('CypherMap', new CypherMap($props)),
                );
                break;
            case Path::class:
                $nodes = [];
                foreach ($value->getNodes() as $node) {
                    $nodes[] = self::autoDetect($node);
                }
                $rels = [];
                foreach ($value->getRelationships() as $i => $rel) {
                    $rels[] = self::autoDetect(new Relationship(
                        $rel->getId(),
                        $value->getNodes()->get($i)->getId(),
                        $value->getNodes()->get($i + 1)->getId(),
                        $rel->getType(),
                        $rel->getProperties()
                    ));
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
                    new CypherObject('CypherMap', new CypherMap($props))
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
