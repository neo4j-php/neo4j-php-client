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

use Closure;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\PointInterface;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Laudis\Neo4j\Http\HttpHelper;
use Laudis\Neo4j\Types\Cartesian3DPoint;
use Laudis\Neo4j\Types\CartesianPoint;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Path;
use Laudis\Neo4j\Types\Relationship;
use Laudis\Neo4j\Types\UnboundRelationship;
use Laudis\Neo4j\Types\WGS843DPoint;
use Laudis\Neo4j\Types\WGS84Point;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use stdClass;
use function strtolower;
use UnexpectedValueException;

/**
 * @psalm-immutable
 *
 * @psalm-import-type OGMTypes from OGMFormatter
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
            'T' => Closure::fromCallable([$this, 'translateDateTime']),
            '@' => Closure::fromCallable([$this, 'translatePoint']),
            '#' => Closure::fromCallable([$this, 'translateBinary']),
            '[]' => Closure::fromCallable([$this, 'translateList']),
            '{}' => Closure::fromCallable([$this, 'translateMap']),
            '()' => Closure::fromCallable([$this, 'translateNode']),
            '->' => Closure::fromCallable([$this, 'translateRightRelationship']),
            '<-' => Closure::fromCallable([$this, 'translateLeftRelationship']),
            '..' => Closure::fromCallable([$this, 'translatePath']),
        ];
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
        if (!isset($this->rawToTypes[$key])) {
            throw new UnexpectedValueException('Unexpected Jolt key: '.$key);
        }

        return $this->rawToTypes[$key]($input);
    }

    /**
     * @return OGMTypes
     */
    private function translateDateTime(string $datetime)
    {
        // TODO; They're in ISO format so shouldn't be too hard
        throw new UnexpectedValueException('Date/time values have not been implemented yet');
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
                (float) $coordinates[2],
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
                (float) $coordinates[2],
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
     * @return array{0: string, 1: string, 2: string} $coordinates
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

        if (count($coordinates) !== 2) {
            throw new UnexpectedValueException('Expected 2 coordinates in string: '.$value);
        }

        return $coordinates;
    }

    /**
     * @return CypherMap<OGMTypes>
     */
    private function translateMap(stdClass $value): CypherMap
    {
        return new CypherMap(
            function () use ($value) {
                /** @var stdClass|null $element */
                foreach ((array) $value as $key => $element) {
                    yield $key => $this->translateJoltType($element);
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
     * @param array{0: int, 1: list<string>, 2: stdClass} $value
     */
    private function translateNode(array $value): Node
    {
        return new Node($value[0], new CypherList($value[1]), $this->translateMap($value[2]));
    }

    /**
     * @param array{0:int, 1: int, 2: string, 3:int, 4: stdClass} $value
     */
    private function translateRightRelationship(array $value): Relationship
    {
        return new Relationship($value[0], $value[1], $value[3], $value[2], $this->translateMap($value[4]));
    }

    /**
     * @param array{0:int, 1: int, 2: string, 3:int, 4: stdClass} $value
     */
    private function translateLeftRelationship(array $value): Relationship
    {
        return new Relationship($value[0], $value[3], $value[1], $value[2], $this->translateMap($value[4]));
    }

    private function translateBinary(): Closure
    {
        throw new UnexpectedValueException('Binary data has not been implemented');
    }
}
