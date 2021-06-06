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

namespace Laudis\Neo4j\Formatter;

use Bolt\structures\Point2D;
use Bolt\structures\Point3D;
use Laudis\Neo4j\Types\Cartesian3DPoint;
use Laudis\Neo4j\Types\CartesianPoint;
use function array_slice;
use Bolt\Bolt;
use Bolt\structures\Date as BoltDate;
use Bolt\structures\DateTime as BoltDateTime;
use Bolt\structures\Duration as BoltDuration;
use Bolt\structures\LocalDateTime as BoltLocalDateTime;
use Bolt\structures\LocalTime as BoltLocalTime;
use Bolt\structures\Node as BoltNode;
use Bolt\structures\Relationship as BoltRelationship;
use Bolt\structures\Time as BoltTime;
use function call_user_func;
use function count;
use DateInterval;
use DateTimeImmutable;
use Ds\Map;
use Ds\Vector;
use function explode;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Types\Duration;
use Laudis\Neo4j\Types\LocalDateTime;
use Laudis\Neo4j\Types\LocalTime;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Relationship;
use Laudis\Neo4j\Types\Time;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use function str_contains;
use function strlen;
use function substr;
use UnexpectedValueException;

/**
 * @see https://neo4j.com/docs/driver-manual/current/cypher-workflow/#driver-type-mapping
 *
 * @psalm-type OGMFormatter = scalar|null|\Ds\Vector|\Ds\Map|Date|DateTime|Duration|Node|Relationship|Path
 * @implements FormatterInterface<Vector<\Ds\Map<string, mixed>>>
 */
final class OGMFormatter implements FormatterInterface
{
    private array $rawToTypes;

    public function __construct()
    {
        $this->rawToTypes = [
            BoltNode::class => [Node::class, 'makeFromBoltNode'],
            BoltDate::class => [Date::class, 'makeFromBoltDate'],
            BoltDuration::class => [Duration::class, 'makeFromBoltDuration'],
            BoltDateTime::class => [DateTime::class, 'makeFromBoltDateTime'],
            BoltTime::class => static fn (BoltTime $time): Time => new Time((float) $time->nanoseconds() / 1000000000),
            BoltLocalDateTime::class => static fn (BoltLocalDateTime $time): LocalDateTime => new LocalDateTime($time->seconds(), $time->nanoseconds()),
            BoltLocalTime::class => static fn (BoltLocalTime $time): LocalTime => new LocalTime($time->nanoseconds()),
            BoltRelationship::class => static fn (BoltRelationship $rel): Relationship => new Relationship($rel->id(), $rel->startNodeId(), $rel->endNodeId(), $rel->type(), new Map($rel->properties())),
            'array' => [$this, 'mapArray'],
            'int' => [$this, 'mapInteger'],
            'null' => static fn (): ?object => null,
            'bool' => static fn (bool $x): bool => $x,
            'string' => static fn (string $x): string => $x,
            'float' => static fn (float $x): float => $x,
            Point2D::class => static fn (Point2D $x) => new CartesianPoint($x->x(), $x->y(), 'cartesian', $x->srid()),
            Point3D::class => static fn (Point3D $x) => new Cartesian3DPoint($x->x(), $x->y(), $x->z(), 'cartesian', $x->srid())
        ];
    }

    public function formatBoltResult(array $meta, iterable $results, Bolt $bolt): CypherList
    {
        $results = array_slice($results, 0, count($results) - 1);

        $tbr = new Vector();

        foreach ($results as $result) {
            $tbr->push($this->formatRow($meta, $result));
        }

        return new CypherList($tbr);
    }

    public function formatHttpResult(ResponseInterface $response, array $body): Vector
    {
        $tbr = new Vector();

        foreach ($body['results'] as $results) {
            $tbr->push($this->buildResult($results));
        }

        return $tbr;
    }

    private function buildResult(array $result): Vector
    {
        $tbr = new Vector();

        $columns = $result['columns'];
        foreach ($result['data'] as $data) {
            $row = $data['row'];
            $meta = $data['meta'];
            $graph = $data['graph'];

            $record = new Map();
            $currentMetaPosition = 0;
            $relationshipCounter = 0;
            foreach ($row as $i => $value) {
                // TODO figure out if the types like DateTime get erased when in a node or relationship. (looks like bolt does it too)
                $record->put($columns[$i], $this->mapHttpValue($value, $meta, $currentMetaPosition, $graph, $relationshipCounter));
            }

            $tbr->push($record);
        }

        return $tbr;
    }

    private function formatRow(array $meta, array $result): Map
    {
        $map = new Map();
        foreach ($meta['fields'] as $i => $column) {
            $map->put($column, $this->mapValueToType($result[$i]));
        }

        return $map;
    }

    public function decorateRequest(RequestInterface $request): RequestInterface
    {
        return $request;
    }

    public function statementConfigOverride(): array
    {
        return [
            'resultDataContents' => ['ROW', 'GRAPH'],
        ];
    }

    private function mapHttpValue($value, array $meta, int &$currentMetaPosition, array $graph, int &$relationshipCounter)
    {
        if (is_array($value)) {
            $currentMeta = $meta[$currentMetaPosition];
            ++$currentMetaPosition;
            $type = $currentMeta['type'] ?? null;
            if ($type === 'relationship') {
                $relationship = $graph['relationships'][$relationshipCounter];
                ++$relationshipCounter;
                $map = new Map();
                foreach ($relationship['properties'] ?? [] as $key => $x) {
                    $map->put($key, $this->mapHttpValue($x, $meta, $currentMetaPosition, $graph, $relationshipCounter));
                }

                return new Relationship((int) $relationship['id'], (int) $relationship['startNode'], (int) $relationship['endNode'], $relationship['type'], $map);
            }
            if ($type === 'point') {
                $pointType = $value['crs']['name'];
                if ($pointType === 'cartesian') {
                    return new CartesianPoint($value['coordinates'][0], $value['coordinates'][1], $value['crs']['name'], $value['crs']['srid']);
                }
            }
            if (isset($value[0])) {
                $tbr = new Vector();
                foreach ($value as $x) {
                    $tbr->push($this->mapHttpValue($x, $meta, $currentMetaPosition, $graph, $relationshipCounter));
                }

                return new CypherList($tbr);
            }

            $tbr = new Map();
            foreach ($value as $key => $x) {
                $tbr->put($key, $this->mapHttpValue($x, $meta, $currentMetaPosition, $graph, $relationshipCounter));
            }

            if ($type === 'node') {
                $labels = [];
                foreach ($graph['nodes'] as $node) {
                    if ((string) $node['id'] === (string) $currentMeta['id']) {
                        $labels = $node['labels'];
                        break;
                    }
                }

                return new Node($currentMeta['id'], new Vector($labels), $tbr);
            }

            return new CypherMap($tbr);
        }
        if (is_string($value)) {
            $type = $meta[$currentMetaPosition]['type'] ?? null;
            ++$currentMetaPosition;
            if ($type === 'duration') {
                if (str_contains($value, '.')) {
                    [$format, $secondsFraction] = explode('.', $value);
                    $nanoseconds = (int) substr($secondsFraction, 6);
                    $microseconds = (int) str_pad((string) ((int) substr($secondsFraction, 0, 6)), 6, '0');
                    $interval = new DateInterval($format.'S');
                    $x = new DateTimeImmutable();
                    $y = $x->add($interval)->modify('+'.$microseconds.' microseconds');
                    $interval = $y->diff($x);
                } else {
                    $nanoseconds = 0;
                    $interval = new DateInterval($value);
                }

                $months = $interval->y * 12 + $interval->m;
                $days = $interval->d;
                $seconds = $interval->h * 60 * 60 + $interval->i * 60 + $interval->s;
                $nanoseconds = (int) ($interval->f * 1000000000) + $nanoseconds;

                return new Duration($months, $days, $seconds, $nanoseconds);
            }
            if ($type === 'date') {
                $epoch = new DateTimeImmutable('@0');
                $diff = DateTimeImmutable::createFromFormat('Y-m-d', (string) $value)->diff($epoch);

                return new Date((int) $diff->format('%a'));
            }
            if ($type === 'time') {
                if (strlen($value) === 6) {
                    $value = substr($value, 0, 5);
                    $values = explode(':', $value);

                    return new Time($values[0] * 60 * 60 + $values[1] * 60);
                }
            }
            if ($type === 'datetime') {
                [$date, $time] = explode('T', $value);
                $tz = null;
                if (str_contains($time, '+')) {
                    [$time, $timezone] = explode('+', $time);
                    [$tzHours, $tzMinutes] = explode(':', $timezone);
                    $tz = (int) $tzHours * 60 * 60 + (int) $tzMinutes * 60;
                }
                [$time, $milliseconds] = explode('.', $time);

                $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date.' '.$time);

                if ($tz !== null) {
                    return new DateTime($date->getTimestamp(), (int) $milliseconds * 1000000, $tz);
                }

                return new DateTime($date->getTimestamp(), (int) $milliseconds * 1000000, 0);
            }
            if ($type === 'localdatetime') {
                [$date, $time] = explode('T', $value);
                [$time, $milliseconds] = explode('.', $time);

                $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date.' '.$time);

                return new LocalDateTime($date->getTimestamp(), (int) $milliseconds * 1000000);
            }
            --$currentMetaPosition;

            return $value;
        }
        if (is_bool($value) || is_float($value) || is_int($value)) {
            return $value;
        }
    }

    private function mapValueToType($value)
    {
        $type = get_debug_type($value);
        if (!isset($this->rawToTypes[$type])) {
            throw new UnexpectedValueException('Cannot handle value of debug type: '.$type);
        }

        return call_user_func($this->rawToTypes[$type], $value);
    }

    private function mapArray($value)
    {
        if (isset($value[0])) {
            $vector = new Vector();
            foreach ($value as $x) {
                $vector->push($this->mapValueToType($x));
            }

            return new CypherList($vector);
        }

        $map = new Map();
        foreach ($value as $key => $x) {
            $map->put($key, $this->mapValueToType($x));
        }

        return new CypherMap($map);
    }

    private function mapInteger($value): int
    {
        return (int) $value;
    }
}
