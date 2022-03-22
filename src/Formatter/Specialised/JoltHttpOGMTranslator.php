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

use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\PointInterface;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Laudis\Neo4j\Http\HttpHelper;
use Laudis\Neo4j\Types\AbstractPropertyObject;
use Laudis\Neo4j\Types\Cartesian3DPoint;
use Laudis\Neo4j\Types\CartesianPoint;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Path;
use Laudis\Neo4j\Types\Relationship;
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
    private array $rawToTypes;

    public function __construct()
    {
        $this->rawToTypes = [
            '?' => static fn (string $value): bool => strtolower($value) === 'true',
            'Z' => static fn (string $value): int => (int) $value,
            'R' => static fn (string $value): float => (float) $value,
            'U' => static fn (string $value): string => $value,
            'T' => fn (string $value): AbstractPropertyObject => $this->translateDateTime($value),
            '@' => fn (string $value): PointInterface => $this->translatePoint($value),
            '#' => static function (string $value) {
                // TODO
                throw new UnexpectedValueException('Binary data has not been implemented');
            },
            '[]' => fn (array $value): CypherList => $this->translateList($value),
            '{}' => fn (stdClass $value): CypherMap => $this->translateMap($value),
            '()' => fn (array $value): Node => new Node($value[0], new CypherList($value[1]), $this->translateMap($value[2])),
            '->' => fn (array $value): Relationship => new Relationship($value[0], $value[1], $value[3], $value[2], $this->translateMap($value[4])),
            '<-' => fn (array $value): Relationship => new Relationship($value[0], $value[3], $value[1], $value[2], $this->translateMap($value[4])),
            '..' => fn (array $value): Path => $this->translatePath($value),
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
        // TODO: Lazy evaluation.
        foreach ($body->results as $result) {
            $fields = $result->header->fields;
            $rows = [];
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

    private function translateJoltType(?stdClass $value)
    {
        if (is_null($value)) {
            return null;
        }

        [$key, $value] = HttpHelper::splitJoltSingleton($value);
        if (!isset($this->rawToTypes[$key])) {
            throw new UnexpectedValueException('Unexpected Jolt key: '.$key);
        }

        return $this->rawToTypes[$key]($value);
    }

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
                $coordinates[0],
                $coordinates[1],
            );
        }
        if ($srid === Cartesian3DPoint::SRID) {
            return new Cartesian3DPoint(
                $coordinates[0],
                $coordinates[1],
                $coordinates[2],
            );
        }
        if ($srid === WGS84Point::SRID) {
            return new WGS84Point(
                $coordinates[0],
                $coordinates[1],
            );
        }
        if ($srid === WGS843DPoint::SRID) {
            return new WGS843DPoint(
                $coordinates[0],
                $coordinates[1],
                $coordinates[2],
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

        return (int) $matches[1];
    }

    private function getCoordinates(string $value): array
    {
        $matches = [];
        if (!preg_match('/^POINT ?(Z?) ?\(([0-9\. ]+)\)$/', $value, $matches)) {
            throw new UnexpectedValueException('Unexpected point coordinates string: '.$value);
        }
        $coordinates = explode(' ', $matches[2]);
        if ($matches[1] === 'Z') {
            if (count($coordinates) !== 3) {
                throw new UnexpectedValueException('Expected 3 coordinates in string: '.$value);
            }
        } else {
            if (count($coordinates) !== 2) {
                throw new UnexpectedValueException('Expected 2 coordinates in string: '.$value);
            }
        }

        return $coordinates;
    }

    private function translateMap(stdClass $value): CypherMap
    {
        return new CypherMap(
            function () use ($value) {
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
                foreach ($value as $element) {
                    yield $this->translateJoltType($element);
                }
            }
        );
    }

    private function translatePath(array $value)
    {
        $nodes = [];
        $relations = [];
        $ids = [];
        foreach ($value as $i => $nodeOrRelation) {
            $nodeOrRelation = $this->translateJoltType($nodeOrRelation);
            if ($i % 2) {
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
}
