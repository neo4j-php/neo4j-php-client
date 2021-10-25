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

use Bolt\structures\Date as BoltDate;
use Bolt\structures\DateTime as BoltDateTime;
use Bolt\structures\Duration as BoltDuration;
use Bolt\structures\LocalDateTime as BoltLocalDateTime;
use Bolt\structures\LocalTime as BoltLocalTime;
use Bolt\structures\Node as BoltNode;
use Bolt\structures\Path as BoltPath;
use Bolt\structures\Point2D as BoltPoint2D;
use Bolt\structures\Point3D as BoltPoint3D;
use Bolt\structures\Relationship as BoltRelationship;
use Bolt\structures\Time as BoltTime;
use Bolt\structures\UnboundRelationship as BoltUnboundRelationship;
use function call_user_func;
use Laudis\Neo4j\Types\Cartesian3DPoint;
use Laudis\Neo4j\Types\CartesianPoint;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\Duration;
use Laudis\Neo4j\Types\LocalDateTime;
use Laudis\Neo4j\Types\LocalTime;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Path;
use Laudis\Neo4j\Types\Relationship;
use Laudis\Neo4j\Types\Time;
use Laudis\Neo4j\Types\UnboundRelationship;
use UnexpectedValueException;

/**
 * Translates Bolt objects to Driver Types.
 *
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @psalm-immutable
 */
final class BoltOGMTranslator
{
    /**
     * @var array<string, callable(mixed):OGMTypes>
     */
    private array $rawToTypes;

    public function __construct()
    {
        $this->rawToTypes = [
            BoltNode::class => [$this, 'makeFromBoltNode'],
            BoltDate::class => [$this, 'makeFromBoltDate'],
            BoltDuration::class => [$this, 'makeFromBoltDuration'],
            BoltDateTime::class => [$this, 'makeFromBoltDateTime'],
            BoltTime::class => [$this, 'makeFromBoltTime'],
            BoltLocalDateTime::class => [$this, 'makeFromBoltLocalDateTime'],
            BoltLocalTime::class => [$this, 'makeFromBoltLocalTime'],
            BoltRelationship::class => [$this, 'makeFromBoltRelationship'],
            BoltUnboundRelationship::class => [$this, 'makeFromBoltUnboundRelationship'],
            BoltPath::class => [$this, 'makeFromBoltPath'],
            BoltPoint2D::class => [$this, 'makeFromBoltPoint2D'],
            BoltPoint3D::class => [$this, 'makeFromBoltPoint3D'],
            'array' => [$this, 'mapArray'],
            'int' => static fn (int $x): int => $x,
            'null' => static fn (): ?object => null,
            'bool' => static fn (bool $x): bool => $x,
            'string' => static fn (string $x): string => $x,
            'float' => static fn (float $x): float => $x,
        ];
    }

    private function makeFromBoltNode(BoltNode $node): Node
    {
        /** @var array<string, OGMTypes> $properties */
        $properties = [];
        /**
         * @var string $name
         * @var mixed  $property
         */
        foreach ($node->properties() as $name => $property) {
            $properties[$name] = $this->mapValueToType($property);
        }

        /**
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        return new Node(
            $node->id(),
            new CypherList($node->labels()),
            new CypherMap($properties)
        );
    }

    private function makeFromBoltDate(BoltDate $date): Date
    {
        return new Date($date->days());
    }

    private function makeFromBoltLocalDateTime(BoltLocalDateTime $time): LocalDateTime
    {
        return new LocalDateTime($time->seconds(), $time->nanoseconds());
    }

    private function makeFromBoltDuration(BoltDuration $duration): Duration
    {
        return new Duration(
            $duration->months(),
            $duration->days(),
            $duration->seconds(),
            $duration->nanoseconds(),
        );
    }

    private function makeFromBoltDateTime(BoltDateTime $datetime): DateTime
    {
        return new DateTime($datetime->seconds(), $datetime->nanoseconds(), $datetime->tz_offset_seconds());
    }

    private function makeFromBoltTime(BoltTime $time): Time
    {
        return new Time((float) $time->nanoseconds() / 1000000000);
    }

    private function makeFromBoltLocalTime(BoltLocalTime $time): LocalTime
    {
        return new LocalTime($time->nanoseconds());
    }

    private function makeFromBoltRelationship(BoltRelationship $rel): Relationship
    {
        /** @var array<string, OGMTypes> $map */
        $map = [];
        /**
         * @var string $key
         * @var mixed  $property
         */
        foreach ($rel->properties() as $key => $property) {
            $map[$key] = $this->mapValueToType($property);
        }

        return new Relationship(
            $rel->id(),
            $rel->startNodeId(),
            $rel->endNodeId(),
            $rel->type(),
            new CypherMap($map)
        );
    }

    private function makeFromBoltUnboundRelationship(BoltUnboundRelationship $rel): UnboundRelationship
    {
        /** @var array<string, OGMTypes> $map */
        $map = [];
        /**
         * @var string $key
         * @var mixed  $property
         */
        foreach ($rel->properties() as $key => $property) {
            $map[$key] = $this->mapValueToType($property);
        }

        return new UnboundRelationship(
            $rel->id(),
            $rel->type(),
            new CypherMap($map)
        );
    }

    private function makeFromBoltPoint2D(BoltPoint2d $x): CartesianPoint
    {
        return new CartesianPoint($x->x(), $x->y(), 'cartesian', $x->srid());
    }

    private function makeFromBoltPoint3D(BoltPoint3D $x): Cartesian3DPoint
    {
        return new Cartesian3DPoint($x->x(), $x->y(), $x->z(), 'cartesian', $x->srid());
    }

    private function makeFromBoltPath(BoltPath $path): Path
    {
        $nodes = [];
        /** @var list<BoltNode> $boltNodes */
        $boltNodes = $path->nodes();
        foreach ($boltNodes as $node) {
            $nodes[] = $this->makeFromBoltNode($node);
        }
        $relationships = [];
        /** @var list<BoltUnboundRelationship> $rels */
        $rels = $path->rels();
        foreach ($rels as $rel) {
            $relationships[] = $this->makeFromBoltUnboundRelationship($rel);
        }
        /** @var list<int> $ids */
        $ids = $path->ids();

        return new Path(
            new CypherList($nodes),
            new CypherList($relationships),
            new CypherList($ids),
        );
    }

    /**
     * @return CypherList<OGMTypes>|CypherMap<OGMTypes>
     */
    private function mapArray(array $value)
    {
        if (isset($value[0])) {
            /** @var array<OGMTypes> $vector */
            $vector = [];
            /** @var mixed $x */
            foreach ($value as $x) {
                $vector[] = $this->mapValueToType($x);
            }

            return new CypherList($vector);
        }

        /** @var array<string, OGMTypes> */
        $map = [];
        /**
         * @var string $key
         * @var mixed  $x
         */
        foreach ($value as $key => $x) {
            $map[$key] = $this->mapValueToType($x);
        }

        return new CypherMap($map);
    }

    /**
     * @param mixed $value
     *
     * @return OGMTypes
     */
    public function mapValueToType($value)
    {
        /** @psalm-suppress ImpureFunctionCall false positive in version php 7.4 */
        $type = get_debug_type($value);
        if (!isset($this->rawToTypes[$type])) {
            throw new UnexpectedValueException('Cannot handle value of debug type: '.$type);
        }

        return call_user_func($this->rawToTypes[$type], $value);
    }
}
