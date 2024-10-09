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

namespace Laudis\Neo4j\Formatter\Specialised;

use Bolt\protocol\v1\structures\Date as BoltDate;
use Bolt\protocol\v1\structures\DateTime as BoltDateTime;
use Bolt\protocol\v1\structures\DateTimeZoneId as BoltDateTimeZoneId;
use Bolt\protocol\v1\structures\Duration as BoltDuration;
use Bolt\protocol\v1\structures\LocalDateTime as BoltLocalDateTime;
use Bolt\protocol\v1\structures\LocalTime as BoltLocalTime;
use Bolt\protocol\v1\structures\Node as BoltNode;
use Bolt\protocol\v1\structures\Path as BoltPath;
use Bolt\protocol\v1\structures\Point2D as BoltPoint2D;
use Bolt\protocol\v1\structures\Point3D as BoltPoint3D;
use Bolt\protocol\v1\structures\Relationship as BoltRelationship;
use Bolt\protocol\v1\structures\Time as BoltTime;
use Bolt\protocol\v1\structures\UnboundRelationship as BoltUnboundRelationship;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Laudis\Neo4j\Types\Abstract3DPoint;
use Laudis\Neo4j\Types\AbstractPoint;
use Laudis\Neo4j\Types\Cartesian3DPoint;
use Laudis\Neo4j\Types\CartesianPoint;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\DateTimeZoneId;
use Laudis\Neo4j\Types\Duration;
use Laudis\Neo4j\Types\LocalDateTime;
use Laudis\Neo4j\Types\LocalTime;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Path;
use Laudis\Neo4j\Types\Relationship;
use Laudis\Neo4j\Types\Time;
use Laudis\Neo4j\Types\UnboundRelationship;
use Laudis\Neo4j\Types\WGS843DPoint;
use Laudis\Neo4j\Types\WGS84Point;
use UnexpectedValueException;

/**
 * Translates Bolt objects to Driver Types.
 *
 * @psalm-import-type OGMTypes from OGMFormatter
 *
 * @psalm-immutable
 *
 * @psalm-pure
 */
final class BoltOGMTranslator
{
    /**
     * @var array<string, pure-callable(mixed):OGMTypes>
     */
    private readonly array $rawToTypes;

    public function __construct()
    {
        /** @psalm-suppress InvalidPropertyAssignmentValue */
        $this->rawToTypes = [
            BoltNode::class => $this->makeFromBoltNode(...),
            BoltDate::class => $this->makeFromBoltDate(...),
            BoltDuration::class => $this->makeFromBoltDuration(...),
            BoltDateTime::class => $this->makeFromBoltDateTime(...),
            BoltTime::class => $this->makeFromBoltTime(...),
            BoltLocalDateTime::class => $this->makeFromBoltLocalDateTime(...),
            BoltLocalTime::class => $this->makeFromBoltLocalTime(...),
            BoltRelationship::class => $this->makeFromBoltRelationship(...),
            BoltUnboundRelationship::class => $this->makeFromBoltUnboundRelationship(...),
            BoltPath::class => $this->makeFromBoltPath(...),
            BoltPoint2D::class => $this->makeFromBoltPoint2D(...),
            BoltPoint3D::class => $this->makeFromBoltPoint3D(...),
            BoltDateTimeZoneId::class => $this->makeBoltTimezoneIdentifier(...),
            'array' => $this->mapArray(...),
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
        foreach ($node->properties as $name => $property) {
            $properties[$name] = $this->mapValueToType($property);
        }

        /** @var ?string|null $elementId */
        $elementId = null;
        if ($node instanceof \Bolt\protocol\v5\structures\Node) {
            $elementId = $node->element_id;
        }
        /**
         * @psalm-suppress MixedArgumentTypeCoercion
         */
        return new Node(
            $node->id,
            new CypherList($node->labels),
            new CypherMap($properties),
            $elementId
        );
    }

    private function makeFromBoltDate(BoltDate $date): Date
    {
        return new Date($date->days);
    }

    private function makeFromBoltLocalDateTime(BoltLocalDateTime $time): LocalDateTime
    {
        return new LocalDateTime($time->seconds, $time->nanoseconds);
    }

    private function makeBoltTimezoneIdentifier(BoltDateTimeZoneId $time): DateTimeZoneId
    {
        /** @var non-empty-string $tzId */
        $tzId = $time->tz_id;

        return new DateTimeZoneId($time->seconds, $time->nanoseconds, $tzId);
    }

    private function makeFromBoltDuration(BoltDuration $duration): Duration
    {
        return new Duration(
            $duration->months,
            $duration->days,
            $duration->seconds,
            $duration->nanoseconds,
        );
    }

    private function makeFromBoltDateTime(BoltDateTime $datetime): DateTime
    {
        return new DateTime(
            $datetime->seconds,
            $datetime->nanoseconds,
            $datetime->tz_offset_seconds,
            !$datetime instanceof \Bolt\protocol\v5\structures\DateTime
        );
    }

    private function makeFromBoltTime(BoltTime $time): Time
    {
        return new Time($time->nanoseconds, $time->tz_offset_seconds);
    }

    private function makeFromBoltLocalTime(BoltLocalTime $time): LocalTime
    {
        return new LocalTime($time->nanoseconds);
    }

    private function makeFromBoltRelationship(BoltRelationship $rel): Relationship
    {
        /** @var array<string, OGMTypes> $map */
        $map = [];
        /**
         * @var string $key
         * @var mixed  $property
         */
        foreach ($rel->properties as $key => $property) {
            $map[$key] = $this->mapValueToType($property);
        }

        /** @var string|null $elementId */
        $elementId = null;
        if ($rel instanceof \Bolt\protocol\v5\structures\Relationship) {
            $elementId = $rel->element_id;
        }

        return new Relationship(
            $rel->id,
            $rel->startNodeId,
            $rel->endNodeId,
            $rel->type,
            new CypherMap($map),
            $elementId
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
        foreach ($rel->properties as $key => $property) {
            $map[$key] = $this->mapValueToType($property);
        }

        $elementId = null;
        if ($rel instanceof \Bolt\protocol\v5\structures\UnboundRelationship) {
            $elementId = $rel->element_id;
        }

        return new UnboundRelationship(
            $rel->id,
            $rel->type,
            new CypherMap($map),
            $elementId
        );
    }

    private function makeFromBoltPoint2D(BoltPoint2d $x): AbstractPoint
    {
        if ($x->srid === CartesianPoint::SRID) {
            return new CartesianPoint($x->x, $x->y);
        } elseif ($x->srid === WGS84Point::SRID) {
            return new WGS84Point($x->x, $x->y);
        }
        throw new UnexpectedValueException('An srid of '.$x->srid.' has been returned, which has not been implemented.');
    }

    private function makeFromBoltPoint3D(BoltPoint3D $x): Abstract3DPoint
    {
        if ($x->srid === Cartesian3DPoint::SRID) {
            return new Cartesian3DPoint($x->x, $x->y, $x->z);
        } elseif ($x->srid === WGS843DPoint::SRID) {
            return new WGS843DPoint($x->x, $x->y, $x->z);
        }
        throw new UnexpectedValueException('An srid of '.$x->srid.' has been returned, which has not been implemented.');
    }

    private function makeFromBoltPath(BoltPath $path): Path
    {
        $nodes = [];
        /** @var list<BoltNode> $boltNodes */
        $boltNodes = $path->nodes;
        foreach ($boltNodes as $node) {
            $nodes[] = $this->makeFromBoltNode($node);
        }
        $relationships = [];
        /** @var list<BoltUnboundRelationship> $rels */
        $rels = $path->rels;
        foreach ($rels as $rel) {
            $relationships[] = $this->makeFromBoltUnboundRelationship($rel);
        }
        /** @var list<int> $ids */
        $ids = $path->ids;

        return new Path(
            new CypherList($nodes),
            new CypherList($relationships),
            new CypherList($ids),
        );
    }

    /**
     * @return CypherList<OGMTypes>|CypherMap<OGMTypes>
     */
    private function mapArray(array $value): CypherList|CypherMap
    {
        if (array_is_list($value)) {
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
     * @return OGMTypes
     */
    public function mapValueToType(mixed $value)
    {
        $type = get_debug_type($value);
        foreach ($this->rawToTypes as $class => $formatter) {
            /** @psalm-suppress ArgumentTypeCoercion */
            if ($type === $class || is_a($value, $class)) {
                return $formatter($value);
            }
        }

        throw new UnexpectedValueException('Cannot handle value of debug type: '.$type);
    }
}
