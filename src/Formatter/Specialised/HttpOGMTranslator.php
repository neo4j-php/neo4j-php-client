<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Formatter\Specialised;

use function array_combine;
use function count;
use function date;
use DateInterval;
use DateTimeImmutable;
use Exception;
use function explode;
use function is_array;
use function is_string;
use Laudis\Neo4j\Contracts\PointInterface;
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
use Laudis\Neo4j\Types\WGS843DPoint;
use Laudis\Neo4j\Types\WGS84Point;
use RuntimeException;
use function sprintf;
use stdClass;
use function str_pad;
use function substr;

/**
 * @psalm-immutable
 *
 * @psalm-type RelationshipArray = array{id: string, type: string, startNode: string, endNode: string, properties: array<string, scalar|null|array<array-key, scalar|null|array>>}
 * @psalm-type NodeArray = array{id: string, labels: list<string>, properties: array<string, scalar|null|array}
 * @psalm-type Meta = array{id?: int, type: string, deleted?: bool}
 * @psalm-type MetaArray = list<Meta|null|list<array{id: int, type: string, deleted: bool}>>
 *
 * @psalm-type CypherResultDataRow = list<array{row: list<scalar|array|null>, meta: MetaArray, graph: array{nodes: list<NodeArray>, relationships: list<RelationshipArray>}}>
 * @psalm-type CypherResult = array{columns: list<string>, data: CypherResultDataRow}
 *
 * @psalm-import-type OGMResults from \Laudis\Neo4j\Formatter\OGMFormatter
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 */
final class HttpOGMTranslator
{
    /**
     * @throws Exception
     *
     * @return CypherList<CypherMap<OGMTypes>>
     */
    public function translateResult(stdClass $result): CypherList
    {
        /** @var list<CypherMap<OGMTypes>> $tbr */
        $tbr = [];

        $columns = $result->columns;
        foreach ($result->data as $data) {
            $meta = HttpMetaInfo::createFromData($data);

            $row = array_combine($columns, (array) $data->row);
            $tbr[] = $this->translateCypherMap($row, $meta)[0];
        }

        return new CypherList($tbr);
    }

    public function translateCypherMap(array $row, HttpMetaInfo $meta): array
    {
        /** @var array<string, OGMTypes> $record */
        $record = [];
        foreach ($row as $key => $value) {
            [$translation, $meta] = $this->translateValue($value, $meta);

            $record[$key] = $translation;
        }

        return [new CypherMap($record), $meta];
    }

    /**
     * @param mixed $value
     *
     * @return array{0: OGMTypes, 1: HttpMetaInfo}
     */
    private function translateValue($value, HttpMetaInfo $meta): array
    {
        if (is_object($value)) {
            return $this->translateObject($value, $meta);
        }

        if (is_array($value)) {
            if ($meta->getCurrentType() === 'path') {
                /**
                 * There are edge cases where multiple paths are wrapped in a list.
                 *
                 * @see OGMFormatterIntegrationTest::testPathMultiple for an example
                 */
                if (is_array($value[0])) {
                    $tbr = [];
                    foreach ($value as $path) {
                        $tbr[] = $this->path($path, $meta->withNestedMeta());
                        $meta = $meta->incrementMeta();
                    }

                    return [new CypherList($tbr), $meta];
                }

                $tbr = $this->path((array) $value, $meta->withNestedMeta());
                $meta = $meta->incrementMeta();

                return [$tbr, $meta];
            }

            return $this->translateCypherList($value, $meta);
        }

        if (is_string($value)) {
            return $this->translateString($value, $meta);
        }

        return [$value, $meta];
    }

    /**
     * @return array{0: Cartesian3DPoint|CartesianPoint|CypherList|CypherMap|Node|Relationship|WGS843DPoint|WGS84Point|Path, 1: HttpMetaInfo}
     */
    private function translateObject(object $value, HttpMetaInfo $meta): array
    {
        $type = $meta->getCurrentType();
        if ($type === 'relationship') {
            return $this->relationship($meta->getCurrentRelationship(), $meta);
        }

        if ($type === 'point') {
            return [$this->translatePoint($value), $meta];
        }

        if ($type === 'node') {
            $node = $meta->currentNode();
            if ($node && json_encode($value, JSON_THROW_ON_ERROR) === json_encode($node->properties, JSON_THROW_ON_ERROR)) {
                $meta = $meta->incrementMeta();
                $map = $this->translateProperties($node->properties);

                return [new Node((int) $node->id, new CypherList($node->labels), $map), $meta];
            }
        }

        return $this->translateCypherMap((array) $value, $meta);
    }

    private function translateProperties(stdClass $properties): CypherMap
    {
        $tbr = [];
        foreach ((array) $properties as $key => $value) {
            if ($value instanceof stdClass) {
                $tbr[$key] = new CypherMap((array) $value);
            } elseif (is_array($value)) {
                $tbr[$key] = new CypherList($value);
            } else {
                $tbr[$key] = $value;
            }
        }

        return new CypherMap($tbr);
    }

    /**
     * @param RelationshipArray $relationship
     */
    private function relationship(stdClass $relationship, HttpMetaInfo $meta): array
    {
        $meta = $meta->incrementMeta();
        $map = $this->translateProperties($relationship->properties);

        $tbr = new Relationship(
            (int) $relationship->id,
            (int) $relationship->startNode,
            (int) $relationship->endNode,
            $relationship->type,
            $map
        );

        return [$tbr, $meta];
    }

    /**
     * @param list<scalar|array|null> $value
     *
     * @return array{0: CypherList<OGMTypes>, 1: HttpMetaInfo}
     */
    private function translateCypherList(array $value, HttpMetaInfo $meta): array
    {
        /** @var array<OGMTypes> $tbr */
        $tbr = [];
        foreach ($value as $x) {
            [$x, $meta] = $this->translateValue($x, $meta);
            $tbr[] = $x;
        }

        return [new CypherList($tbr), $meta->incrementMeta()];
    }

    /**
     * @param list<stdClass>          $meta
     * @param list<NodeArray>         $nodes
     * @param list<RelationshipArray> $relationship
     */
    private function path(array $value, HttpMetaInfo $meta): Path
    {
        $nodes = [];
        /** @var list<int> $ids */
        $ids = [];
        $rels = [];

        foreach ($value as $x) {
            $ids[] = $meta->currentMeta()->id;
            [$x, $meta] = $this->translateObject($x, $meta);
            if ($x instanceof Node) {
                $nodes[] = $x;
            } else {
                $rels[] = $x;
            }
        }

        return new Path(new CypherList($nodes), new CypherList($rels), new CypherList($ids));
    }

    /**
     * @param NodeArray $nodes
     */
    private function translateNode(array $node): Node
    {
        return new Node($node['id'], new CypherList($node['labels']), $this->translateProperties($node['properties']));
    }

    /**
     * @return CartesianPoint|Cartesian3DPoint|WGS843DPoint|WGS84Point
     */
    private function translatePoint(stdClass $value): PointInterface
    {
        /** array{type: 'point', coordinates: array{0: float, 1: float, 2?:float}, crs: array{srid: int, type: string, name: 'cartesian'|'cartesian-3d'|'wgs-84'|'wgs-84-3d', properties: array<string, string>}} */
        $pointType = $value->crs->name;
        if ($pointType === 'cartesian') {
            return new CartesianPoint(
                $value->coordinates[0],
                $value->coordinates[1],
                $value->crs->name,
                $value->crs->srid
            );
        }
        if ($pointType === 'cartesian-3d') {
            return new Cartesian3DPoint(
                $value->coordinates[0],
                $value->coordinates[1],
                $value->coordinates[2],
                $value->crs->name,
                $value->crs->srid
            );
        }
        if ($pointType === 'wgs-84') {
            return new WGS84Point(
                $value->coordinates[0],
                $value->coordinates[1],
                $value->coordinates[0],
                $value->coordinates[1],
                $value->crs->name,
                $value->crs->srid
            );
        }

        return new WGS843DPoint(
            $value->coordinates[0],
            $value->coordinates[1],
            $value->coordinates[2],
            $value->coordinates[0],
            $value->coordinates[1],
            $value->coordinates[2],
            $value->crs->name,
            $value->crs->srid
        );
    }

    /**
     * @throws Exception
     *
     * @return array{0: string|Date|DateTime|Duration|LocalDateTime|LocalTime|Time, 1: HttpMetaInfo}
     */
    public function translateString(string $value, HttpMetaInfo $meta): array
    {
        switch ($meta->getCurrentType()) {
            case 'duration':
                $meta = $meta->incrementMeta();
                $tbr = [$this->translateDuration($value), $meta];
                break;
            case 'datetime':
                $meta = $meta->incrementMeta();
                $tbr = [$this->translateDateTime($value), $meta];
                break;
            case 'date':
                $meta = $meta->incrementMeta();
                $tbr = [$this->translateDate($value), $meta];
                break;
            case 'time':
                $meta = $meta->incrementMeta();
                $tbr = [$this->translateTime($value), $meta];
                break;
            case 'localdatetime':
                $meta = $meta->incrementMeta();
                $tbr = [$this->translateLocalDateTime($value), $meta];
                break;
            case 'localtime':
                $meta = $meta->incrementMeta();
                $tbr = [$this->translateLocalTime($value), $meta];
                break;
            default:
                $tbr = [$value, $meta];
                break;
        }

        return $tbr;
    }

    /**
     * @throws Exception
     */
    private function translateDuration(string $value): Duration
    {
        /** @psalm-suppress ImpureFunctionCall false positive in version php 7.4 */
        if (str_contains($value, '.')) {
            [$format, $secondsFraction] = explode('.', $value);
            $nanoseconds = (int) substr($secondsFraction, 6);
            $microseconds = (int) str_pad((string) ((int) substr($secondsFraction, 0, 6)), 6, '0');
            $interval = new DateInterval($format.'S');
            $x = new DateTimeImmutable();
            $interval = $x->add($interval)->modify('+'.$microseconds.' microseconds')->diff($x);
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

    private function translateDate(string $value): Date
    {
        $epoch = new DateTimeImmutable('@0');
        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($dateTime === false) {
            throw new RuntimeException(sprintf('Could not create date from format "Y-m-d" and %s', $value));
        }

        $diff = $dateTime->diff($epoch);

        /** @psalm-suppress ImpureMethodCall */
        return new Date((int) $diff->format('%a'));
    }

    private function translateTime(string $value): Time
    {
        $value = substr($value, 0, 5);
        $values = explode(':', $value);

        /** @psalm-suppress PossiblyUndefinedIntArrayOffset */
        return new Time(((int) $values[0]) * 60 * 60 + ((int) $values[1]) * 60);
    }

    /**
     * @throws Exception
     */
    private function translateDateTime(string $value): DateTime
    {
        [$date, $time] = explode('T', $value);
        $tz = null;
        /** @psalm-suppress ImpureFunctionCall false positive in version php 7.4 */
        if (str_contains($time, '+')) {
            [$time, $timezone] = explode('+', $time);
            [$tzHours, $tzMinutes] = explode(':', $timezone);
            $tz = (int) $tzHours * 60 * 60 + (int) $tzMinutes * 60;
        }
        [$time, $milliseconds] = explode('.', $time);

        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date.' '.$time);
        if ($dateTime === false) {
            throw new RuntimeException(sprintf('Could not create date from format "Y-m-d H:i:s" and %s', $date.' '.$time));
        }

        if ($tz !== null) {
            return new DateTime($dateTime->getTimestamp(), (int) $milliseconds * 1000000, $tz);
        }

        return new DateTime($dateTime->getTimestamp(), (int) $milliseconds * 1000000, 0);
    }

    private function translateLocalDateTime(string $value): LocalDateTime
    {
        [$date, $time] = explode('T', $value);
        [$time, $milliseconds] = explode('.', $time);

        $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $date.' '.$time);
        if ($dateTime === false) {
            throw new RuntimeException(sprintf('Could not create date from format "Y-m-d H:i:s" and %s', $date.' '.$time));
        }

        return new LocalDateTime($dateTime->getTimestamp(), (int) $milliseconds * 1000000);
    }

    /**
     * @psalm-suppress all
     *
     * @throws Exception
     */
    private function translateLocalTime(string $value): LocalTime
    {
        $timestamp = (new DateTimeImmutable($value))->getTimestamp();

        $hours = (int) date('H', $timestamp);
        $minutes = (int) date('i', $timestamp);
        $seconds = (int) date('s', $timestamp);
        $milliseconds = 0;

        $values = explode('.', $value);
        if (count($values) > 1) {
            $milliseconds = $values[1];
        }

        $totalSeconds = ($hours * 3600) + ($minutes * 60) + $seconds + ($milliseconds / 1000);

        return new LocalTime((int) $totalSeconds * 1000000000);
    }
}
