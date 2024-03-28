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

use Closure;

use const DATE_ATOM;

use DateInterval;
use DateTimeImmutable;

use function is_array;

use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\PointInterface;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Laudis\Neo4j\Http\HttpHelper;
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
use Laudis\Neo4j\Types\WGS843DPoint;
use Laudis\Neo4j\Types\WGS84Point;

use function preg_match;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use stdClass;

use function str_pad;

use const STR_PAD_RIGHT;

use function str_replace;
use function str_starts_with;
use function strtolower;

use UnexpectedValueException;

/**
 * @psalm-immutable
 *
 * @psalm-import-type OGMTypes from OGMFormatter
 *
 * @psalm-suppress PossiblyUndefinedArrayOffset
 */
final class JoltHttpOGMTranslator
{
    /** @var array<string, pure-callable(mixed):OGMTypes> */
    private array $rawToTypes;

    public function __construct()
    {
        /** @psalm-suppress InvalidPropertyAssignmentValue */
        $this->rawToTypes = [
            '?' => static fn (string $value): bool => strtolower($value) === 'true',
            'Z' => static fn (string $value): int => (int) $value,
            'R' => static fn (string $value): float => (float) $value,
            'U' => static fn (string $value): string => $value,
            'T' => Closure::fromCallable($this->translateDateTime(...)),
            '@' => Closure::fromCallable($this->translatePoint(...)),
            '#' => Closure::fromCallable($this->translateBinary(...)),
            '[]' => Closure::fromCallable($this->translateList(...)),
            '{}' => Closure::fromCallable($this->translateMap(...)),
            '()' => Closure::fromCallable($this->translateNode(...)),
            '->' => Closure::fromCallable($this->translateRightRelationship(...)),
            '<-' => Closure::fromCallable($this->translateLeftRelationship(...)),
            '..' => Closure::fromCallable($this->translatePath(...)),
        ];
    }

    public function decorateRequest(RequestInterface $request): RequestInterface
    {
        /** @psalm-suppress ImpureMethodCall */
        return $request->withHeader(
            'Accept',
            'application/vnd.neo4j.jolt+json-seq;strict=true;charset=UTF-8'
        );
    }

    /**
     * @return array{resultDataContents?: list<'GRAPH'|'ROW'|'REST'>, includeStats?:bool}
     */
    public function statementConfigOverride(): array
    {
        return [];
    }

    /**
     * @return CypherList<CypherList<CypherMap<OGMTypes>>>
     */
    public function formatHttpResult(
        ResponseInterface $response,
        stdClass $body,
        ConnectionInterface $connection,
        float $resultsAvailableAfter,
        float $resultsConsumedAfter,
        iterable $statements
    ): CypherList {
        $allResults = [];
        /** @var stdClass $result */
        foreach ($body->results as $result) {
            /** @var stdClass $header */
            $header = $result->header;
            /** @var list<string> $fields */
            $fields = $header->fields;
            $rows = [];

            /** @var list<stdClass> $data */
            foreach ($result->data as $data) {
                $row = [];
                foreach ($data as $key => $value) {
                    $row[$fields[$key]] = $this->translateJoltType($value);
                }
                $rows[] = new CypherMap($row);
            }
            $allResults[] = new CypherList($rows);
        }

        return new CypherList($allResults);
    }

    /**
     * @return OGMTypes
     */
    private function translateJoltType(?stdClass $value)
    {
        if (is_null($value)) {
            return null;
        }

        /** @var mixed $input */
        [$key, $input] = HttpHelper::splitJoltSingleton($value);
        if (!array_key_exists($key, $this->rawToTypes)) {
            throw new UnexpectedValueException('Unexpected Jolt key: '.$key);
        }

        return $this->rawToTypes[$key]($input);
    }

    /**
     * Assumes that 2D points are of the form "SRID=$srid;POINT($x $y)" and 3D points are of the form "SRID=$srid;POINT Z($x $y $z)".
     *
     * @throws UnexpectedValueException
     */
    private function translatePoint(string $value): PointInterface
    {
        [$srid, $coordinates] = explode(';', $value, 2);

        $srid = $this->getSRID($srid);
        $coordinates = $this->getCoordinates($coordinates);

        if ($srid === CartesianPoint::SRID) {
            return new CartesianPoint(
                (float) $coordinates[0],
                (float) $coordinates[1],
            );
        }
        if ($srid === Cartesian3DPoint::SRID) {
            return new Cartesian3DPoint(
                (float) $coordinates[0],
                (float) $coordinates[1],
                (float) ($coordinates[2] ?? 0.0),
            );
        }
        if ($srid === WGS84Point::SRID) {
            return new WGS84Point(
                (float) $coordinates[0],
                (float) $coordinates[1],
            );
        }
        if ($srid === WGS843DPoint::SRID) {
            return new WGS843DPoint(
                (float) $coordinates[0],
                (float) $coordinates[1],
                (float) ($coordinates[2] ?? 0.0),
            );
        }
        throw new UnexpectedValueException('A point with srid '.$srid.' has been returned, which has not been implemented.');
    }

    private function getSRID(string $value): int
    {
        $matches = [];
        if (!preg_match('/^SRID=([0-9]+)$/', $value, $matches)) {
            throw new UnexpectedValueException('Unexpected SRID string: '.$value);
        }

        /** @var array{0: string, 1: string} $matches */
        return (int) $matches[1];
    }

    /**
     * @return array{0: string, 1: string, 2?: string} $coordinates
     */
    private function getCoordinates(string $value): array
    {
        $matches = [];
        if (!preg_match('/^POINT ?(Z?) ?\(([0-9. ]+)\)$/', $value, $matches)) {
            throw new UnexpectedValueException('Unexpected point coordinates string: '.$value);
        }
        /** @var array{0: string, 1: string, 2: string} $matches */
        $coordinates = explode(' ', $matches[2]);
        if ($matches[1] === 'Z' && count($coordinates) !== 3) {
            throw new UnexpectedValueException('Expected 3 coordinates in string: '.$value);
        }

        if ($matches[1] !== 'Z' && count($coordinates) !== 2) {
            throw new UnexpectedValueException('Expected 2 coordinates in string: '.$value);
        }

        /** @var array{0: string, 1: string, 2?: string} */
        return $coordinates;
    }

    /**
     * @return CypherMap<OGMTypes>
     */
    private function translateMap(stdClass $value): CypherMap
    {
        return new CypherMap(
            function () use ($value) {
                /** @var stdClass|array|null $element */
                foreach ((array) $value as $key => $element) {
                    // There is an odd case in the JOLT protocol when dealing with properties in a node.
                    // Lists appear not to receive a composite type label,
                    // which is why we have to handle them specifically here.
                    // @see https://github.com/neo4j/neo4j/issues/12858
                    if (is_array($element)) {
                        yield $key => new CypherList($element);
                    } else {
                        yield $key => $this->translateJoltType($element);
                    }
                }
            }
        );
    }

    private function translateList(array $value): CypherList
    {
        return new CypherList(
            function () use ($value) {
                /** @var stdClass|null $element */
                foreach ($value as $element) {
                    yield $this->translateJoltType($element);
                }
            }
        );
    }

    /**
     * @param list<stdClass> $value
     */
    private function translatePath(array $value): Path
    {
        $nodes = [];
        /** @var list<UnboundRelationship> $relations */
        $relations = [];
        $ids = [];
        foreach ($value as $nodeOrRelation) {
            /** @var Node|Relationship $nodeOrRelation */
            $nodeOrRelation = $this->translateJoltType($nodeOrRelation);

            if ($nodeOrRelation instanceof Relationship) {
                $relations[] = $nodeOrRelation;
            } else {
                $nodes[] = $nodeOrRelation;
            }

            $ids[] = $nodeOrRelation->getId();
        }

        return new Path(new CypherList($nodes), new CypherList($relations), new CypherList($ids));
    }

    /**
     * @param array{0: int, 1: list<string>, 2: stdClass} $value
     */
    private function translateNode(array $value): Node
    {
        return new Node($value[0], new CypherList($value[1]), $this->translateMap($value[2]), null);
    }

    /**
     * @param array{0:int, 1: int, 2: string, 3:int, 4: stdClass} $value
     */
    private function translateRightRelationship(array $value): Relationship
    {
        return new Relationship($value[0], $value[1], $value[3], $value[2], $this->translateMap($value[4]), null);
    }

    /**
     * @param array{0:int, 1: int, 2: string, 3:int, 4: stdClass} $value
     */
    private function translateLeftRelationship(array $value): Relationship
    {
        return new Relationship($value[0], $value[3], $value[1], $value[2], $this->translateMap($value[4]), null);
    }

    private function translateBinary(): Closure
    {
        throw new UnexpectedValueException('Binary data has not been implemented');
    }

    private const TIME_REGEX = '(?<hours>\d{2}):(?<minutes>\d{2}):(?<seconds>\d{2})((\.)(?<nanoseconds>\d+))?';
    private const DATE_REGEX = '(?<date>[\-−]?\d+-\d{2}-\d{2})';
    private const ZONE_REGEX = '(?<zone>.+)';

    /**
     * @psalm-suppress ImpureMethodCall
     * @psalm-suppress ImpureFunctionCall
     * @psalm-suppress PossiblyFalseReference
     */
    private function translateDateTime(string $datetime): Date|LocalDateTime|LocalTime|DateTime|Duration|Time
    {
        if (preg_match('/^'.self::DATE_REGEX.'$/u', $datetime, $matches)) {
            $days = $this->daysFromMatches($matches);

            return new Date($days);
        }

        if (preg_match('/^'.self::TIME_REGEX.'$/u', $datetime, $matches)) {
            $nanoseconds = $this->nanosecondsFromMatches($matches);

            return new LocalTime($nanoseconds);
        }

        if (preg_match('/^'.self::TIME_REGEX.self::ZONE_REGEX.'$/u', $datetime, $matches)) {
            $nanoseconds = $this->nanosecondsFromMatches($matches);

            $offset = $this->offsetFromMatches($matches);

            return new Time($nanoseconds, $offset);
        }

        if (preg_match('/^'.self::DATE_REGEX.'T'.self::TIME_REGEX.'$/u', $datetime, $matches)) {
            $nanoseconds = $this->nanosecondsFromMatches($matches);
            $seconds = $this->secondsInDaysFromMatches($matches);

            [$seconds, $nanoseconds] = $this->addNanoSecondsToSeconds($nanoseconds, $seconds);

            return new LocalDateTime($seconds, $nanoseconds);
        }

        if (preg_match('/^'.self::DATE_REGEX.'T'.self::TIME_REGEX.self::ZONE_REGEX.'$/u', $datetime, $matches)) {
            $nanoseconds = $this->nanosecondsFromMatches($matches);
            $seconds = $this->secondsInDaysFromMatches($matches);

            [$seconds, $nanoseconds] = $this->addNanoSecondsToSeconds($nanoseconds, $seconds);

            $offset = $this->offsetFromMatches($matches);

            return new DateTime($seconds, $nanoseconds, $offset, true);
        }

        if (str_starts_with($datetime, 'P')) {
            return $this->durationFromFormat($datetime);
        }

        throw new UnexpectedValueException(sprintf('Could not handle date/time "%s"', $datetime));
    }

    private function nanosecondsFromMatches(array $matches): int
    {
        /** @var array{0: string, hours: string, minutes: string, seconds: string, nanoseconds?:  string} $matches */
        ['hours' => $hours, 'minutes' => $minutes, 'seconds' => $seconds] = $matches;
        $seconds = (((int) $hours) * 60 * 60) + (((int) $minutes) * 60) + ((int) $seconds);

        $nanoseconds = $matches['nanoseconds'] ?? '0';
        $nanoseconds = str_pad($nanoseconds, 9, '0', STR_PAD_RIGHT);

        return $seconds * 1000 * 1000 * 1000 + (int) $nanoseconds;
    }

    private function offsetFromMatches(array $matches): int
    {
        /** @var array{zone: string} $matches */
        $zone = $matches['zone'];

        if (preg_match('/(\d{2}):(\d{2})/', $zone, $matches)) {
            /** @var array{0: string, 1: string, 2: string} $matches */
            return ((int) $matches[1]) * 60 * 60 + (int) $matches[2] * 60;
        }

        return 0;
    }

    private function daysFromMatches(array $matches): int
    {
        /** @var array{date: string} $matches */
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $matches['date']);
        if ($date === false) {
            throw new RuntimeException(sprintf('Cannot create DateTime from "%s" in format "Y-m-d"', $matches['date']));
        }

        /** @psalm-suppress ImpureMethodCall */
        return (int) $date->diff(new DateTimeImmutable('@0'))->format('%a');
    }

    private function secondsInDaysFromMatches(array $matches): int
    {
        /** @var array{date: string} $matches */
        $date = DateTimeImmutable::createFromFormat(DATE_ATOM, $matches['date'].'T00:00:00+00:00');
        if ($date === false) {
            throw new RuntimeException(sprintf('Cannot create DateTime from "%s" in format "Y-m-d"', $matches['date']));
        }

        return $date->getTimestamp();
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function addNanoSecondsToSeconds(int $nanoseconds, int $seconds): array
    {
        $seconds += (int) ($nanoseconds / 1000 / 1000 / 1000);
        $nanoseconds %= 1_000_000_000;

        return [$seconds, $nanoseconds];
    }

    /**
     * @psalm-suppress ImpureMethodCall
     */
    private function durationFromFormat(string $datetime): Duration
    {
        $nanoseconds = 0;
        // PHP date interval does not understand fractions of a second.
        if (preg_match('/\.(?<nanoseconds>\d+)S/u', $datetime, $matches)) {
            /** @var array{0: string, nanoseconds: string} $matches */
            $nanoseconds = (int) str_pad($matches['nanoseconds'], 9, '0', STR_PAD_RIGHT);

            $datetime = str_replace($matches[0], 'S', $datetime);
        }

        $interval = new DateInterval($datetime);
        $months = (int) $interval->format('%y') * 12 + (int) $interval->format('%m');
        $days = (int) $interval->format('%d');
        $seconds = (int) $interval->format('%h') * 60 * 60 + (int) $interval->format('%i') * 60 + (int) $interval->format('%s');

        return new Duration($months, $days, $seconds, $nanoseconds);
    }
}
