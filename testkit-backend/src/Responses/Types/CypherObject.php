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
     * @param CypherList|CypherMap|int|bool|float|string|Node|Relationship|Path|null $value
     */
    public static function autoDetect($value): TestkitResponseInterface
    {
        switch (get_debug_type($value)) {
            case null:
                $tbr = new CypherObject('CypherNull', $value);
                break;
            case CypherList::class:
                $tbr = new CypherObject('CypherList', $value);
                break;
            case CypherMap::class:
                $tbr = new CypherObject('CypherMap', $value);
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
                $tbr = new CypherNode($value->id(), $value->labels(), $value->properties());
                break;
            case Relationship::class:
                $tbr = new CypherRelationship(
                    $value->getId(),
                    $value->getStartNodeId(),
                    $value->getEndNodeId(),
                    $value->getType(),
                    $value->getProperties()
                );
                break;
            case Path::class:
                $tbr = new CypherPath($value->getNodes(), $value->getRelationships());
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
