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

namespace Laudis\Neo4j\Tests\Integration;

use function compact;
use DateInterval;
use Exception;
use function json_encode;
use JsonException;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\PointInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Formatter\OGMFormatter;
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
use function range;
use function sprintf;
use function str_contains;

/**
 * @psalm-import-type OGMResults from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @extends EnvironmentAwareIntegrationTest<OGMResults>
 *
 * @psalm-suppress MixedArrayAccess
 */
final class OGMFormatterIntegrationTest extends EnvironmentAwareIntegrationTest
{
    protected static function formatter(): FormatterInterface
    {
        /** @psalm-suppress InvalidReturnStatement */
        return OGMFormatter::create();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testNull(string $alias): void
    {
        $results = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run('RETURN null as x');
        }, $alias);

        self::assertNull($results->first()->get('x'));
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws JsonException
     * @throws JsonException
     * @throws JsonException
     * @throws JsonException
     */
    public function testList(string $alias): void
    {
        $results = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run('RETURN range(5, 15) as list, range(16, 35) as list2');
        }, $alias);

        $list = $results->first()->get('list');
        $list2 = $results->first()->get('list2');

        self::assertInstanceOf(CypherList::class, $list);
        self::assertInstanceOf(CypherList::class, $list2);
        self::assertEquals(range(5, 15), $list->toArray());
        self::assertEquals(range(16, 35), $list2->toArray());
        self::assertEquals(json_encode(range(5, 15), JSON_THROW_ON_ERROR), json_encode($list, JSON_THROW_ON_ERROR));
        self::assertEquals(json_encode(range(16, 35), JSON_THROW_ON_ERROR), json_encode($list2, JSON_THROW_ON_ERROR));
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws JsonException
     */
    public function testMap(string $alias): void
    {
        $map = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run('RETURN {a: "b", c: "d"} as map')->first()->get('map');
        }, $alias);
        self::assertInstanceOf(CypherMap::class, $map);
        self::assertEquals(['a' => 'b', 'c' => 'd'], $map->toArray());
        self::assertEquals(json_encode(['a' => 'b', 'c' => 'd'], JSON_THROW_ON_ERROR), json_encode($map, JSON_THROW_ON_ERROR));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testBoolean(string $alias): void
    {
        $results = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run('RETURN true as bool1, false as bool2');
        }, $alias);

        self::assertEquals(1, $results->count());
        self::assertIsBool($results->first()->get('bool1'));
        self::assertIsBool($results->first()->get('bool2'));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testInteger(string $alias): void
    {
        $results = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run(<<<CYPHER
UNWIND [{num: 1}, {num: 2}, {num: 3}] AS x
RETURN x.num
ORDER BY x.num ASC
CYPHER
            );
        }, $alias);

        self::assertEquals(3, $results->count());
        self::assertEquals(1, $results[0]['x.num']);
        self::assertEquals(2, $results[1]['x.num']);
        self::assertEquals(3, $results[2]['x.num']);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testFloat(string $alias): void
    {
        $results = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run('RETURN 0.1 AS float');
        }, $alias);

        self::assertIsFloat($results->first()->get('float'));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testString(string $alias): void
    {
        $results = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run('RETURN "abc" AS string');
        }, $alias);

        self::assertIsString($results->first()->get('string'));
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws JsonException
     */
    public function testDate(string $alias): void
    {
        $results = $this->getClient()->transaction(function (TransactionInterface $tsx) {
            $query = $this->articlesQuery();
            $query .= 'RETURN article.datePublished as published_at';

            return $tsx->run($query);
        }, $alias);

        self::assertEquals(3, $results->count());

        $publishedAt = $results[0]['published_at'];
        self::assertInstanceOf(Date::class, $publishedAt);
        self::assertEquals(18048, $publishedAt->getDays());
        self::assertEquals(
            json_encode(['days' => 18048], JSON_THROW_ON_ERROR),
            json_encode($publishedAt, JSON_THROW_ON_ERROR));
        self::assertEquals(18048, $publishedAt->days);

        self::assertInstanceOf(Date::class, $results[1]['published_at']);
        self::assertEquals(18049, $results[1]['published_at']->getDays());
        self::assertEquals(
            json_encode(['days' => 18049], JSON_THROW_ON_ERROR),
            json_encode($results[1]['published_at'], JSON_THROW_ON_ERROR));

        self::assertInstanceOf(Date::class, $results[2]['published_at']);
        self::assertEquals(18742, $results[2]['published_at']->getDays());
        self::assertEquals(
            json_encode(['days' => 18742], JSON_THROW_ON_ERROR),
            json_encode($results[2]['published_at'], JSON_THROW_ON_ERROR));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testTime(string $alias): void
    {
        $results = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run('RETURN time("12:00:00.000000000") AS time');
        }, $alias);

        $time = $results->first()->get('time');
        self::assertInstanceOf(Time::class, $time);
        self::assertEquals(12.0 * 60 * 60 * 1000000000, $time->getNanoSeconds());
        self::assertEquals(12.0 * 60 * 60 * 1000000000, $time->nanoSeconds);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testLocalTime(string $alias): void
    {
        $results = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run('RETURN localtime("12") AS time');
        }, $alias);

        /** @var LocalTime $time */
        $time = $results->first()->get('time');
        self::assertInstanceOf(LocalTime::class, $time);
        self::assertEquals(43200000000000, $time->getNanoseconds());

        $results = $this->getClient()->run('RETURN localtime("09:23:42.000") AS time', [], $alias);

        /** @var LocalTime $time */
        $time = $results->first()->get('time');
        self::assertInstanceOf(LocalTime::class, $time);
        self::assertEquals(33822000000000, $time->getNanoseconds());
        self::assertEquals(33822000000000, $time->nanoseconds);
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws JsonException
     * @throws JsonException
     */
    public function testDateTime(string $alias): void
    {
        $results = $this->getClient()->transaction(function (TransactionInterface $tsx) {
            $query = $this->articlesQuery();
            $query .= 'RETURN article.created as created_at';

            return $tsx->run($query);
        }, $alias);

        self::assertEquals(3, $results->count());

        $createdAt = $results[0]['created_at'];
        self::assertInstanceOf(DateTime::class, $createdAt);
        self::assertEquals(1559414432, $createdAt->getSeconds());
        self::assertEquals(142000000, $createdAt->getNanoseconds());
        self::assertEquals(3600, $createdAt->getTimeZoneOffsetSeconds());
        self::assertEquals(1559414432, $createdAt->seconds);
        self::assertEquals(142000000, $createdAt->nanoseconds);
        self::assertEquals(3600, $createdAt->tzOffsetSeconds);
        self::assertEquals('{"seconds":1559414432,"nanoseconds":142000000,"tzOffsetSeconds":3600}', json_encode($createdAt, JSON_THROW_ON_ERROR));

        self::assertInstanceOf(DateTime::class, $results[1]['created_at']);
        self::assertEquals(1559471012, $results[1]['created_at']->getSeconds());
        self::assertEquals(122000000, $results[1]['created_at']->getNanoseconds());
        self::assertEquals('{"seconds":1559471012,"nanoseconds":122000000,"tzOffsetSeconds":3600}', json_encode($results[1]['created_at'], JSON_THROW_ON_ERROR));

        self::assertInstanceOf(DateTime::class, $results[2]['created_at']);
        self::assertGreaterThan(0, $results[2]['created_at']->getSeconds());
        self::assertGreaterThan(0, $results[2]['created_at']->getNanoseconds());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testLocalDateTime(string $alias): void
    {
        $result = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run('RETURN localdatetime() as local')->first()->get('local');
        }, $alias);

        self::assertInstanceOf(LocalDateTime::class, $result);
        $date = $result->toDateTime();
        self::assertEquals($result->getSeconds(), $date->getTimestamp());
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws JsonException
     * @throws Exception
     */
    public function testDuration(string $alias): void
    {
        $results = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run(<<<CYPHER
UNWIND [
  duration({days: 14, hours:16, minutes: 12}),
  duration({months: 5, days: 1.5}),
  duration({months: 0.75}),
  duration({weeks: 2.5}),
  duration({minutes: 1.5, seconds: 1, milliseconds: 123, microseconds: 456, nanoseconds: 789}),
  duration({minutes: 1.5, seconds: 1, nanoseconds: 123456789})
] AS aDuration
RETURN aDuration
CYPHER
            );
        }, $alias);

        self::assertEquals(6, $results->count());
        self::assertEquals(new Duration(0, 14, 58320, 0), $results[0]['aDuration']);
        $duration = $results[1]['aDuration'];
        self::assertInstanceOf(Duration::class, $duration);
        self::assertEquals(new Duration(5, 1, 43200, 0), $duration);
        self::assertEquals(5, $duration->months);
        self::assertEquals(1, $duration->days);
        self::assertEquals(43200, $duration->seconds);
        self::assertEquals(0, $duration->nanoseconds);
        self::assertEquals(new Duration(0, 22, 71509, 500000000), $results[2]['aDuration']);
        self::assertEquals(new Duration(0, 17, 43200, 0), $results[3]['aDuration']);
        self::assertEquals(new Duration(0, 0, 91, 123456789), $results[4]['aDuration']);
        self::assertEquals(new Duration(0, 0, 91, 123456789), $results[5]['aDuration']);

        self::assertEquals(5, $duration->getMonths());
        self::assertEquals(1, $duration->getDays());
        self::assertEquals(43200, $duration->getSeconds());
        self::assertEquals(0, $duration->getNanoseconds());
        $interval = new DateInterval(sprintf('P%dM%dDT%dS', 5, 1, 43200));
        self::assertEquals($interval, $duration->toDateInterval());
        self::assertEquals('{"months":5,"days":1,"seconds":43200,"nanoseconds":0}', json_encode($duration, JSON_THROW_ON_ERROR));
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws JsonException
     */
    public function testPoint(string $alias): void
    {
        $result = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run('RETURN point({x: 3, y: 4}) AS point');
        }, $alias);
        self::assertInstanceOf(CypherList::class, $result);
        $row = $result->first();
        self::assertInstanceOf(CypherMap::class, $row);
        $point = $row->get('point');

        self::assertInstanceOf(CartesianPoint::class, $point);
        self::assertEquals(3.0, $point->getX());
        self::assertEquals(4.0, $point->getY());
        self::assertEquals('cartesian', $point->getCrs());
        self::assertGreaterThan(0, $point->getSrid());
        self::assertEquals(3.0, $point->x);
        self::assertEquals(4.0, $point->y);
        self::assertEquals('cartesian', $point->crs);
        self::assertGreaterThan(0, $point->srid);
        self::assertEquals(
            json_encode([
                'x' => 3,
                'y' => 4,
                'crs' => 'cartesian',
                'srid' => 7203,
            ], JSON_THROW_ON_ERROR),
            json_encode($point, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws JsonException
     * @throws JsonException
     * @throws JsonException
     * @throws JsonException
     */
    public function testNode(string $alias): void
    {
        $uuid = 'cc60fd69-a92b-47f3-9674-2f27f3437d66';
        $email = 'a@b.c';
        $type = 'pepperoni';

        $results = $this->getClient()->transaction(static function (TransactionInterface $tsx) use ($email, $uuid, $type) {
            return $tsx->run(
                'MERGE (u:User{email: $email})-[:LIKES]->(p:Food:Pizza {type: $type}) ON CREATE SET u.uuid=$uuid RETURN u, p',
                compact('email', 'uuid', 'type')
            );
        }, $alias);

        self::assertEquals(1, $results->count());

        /** @var Node $u */
        $u = $results[0]['u'];
        self::assertInstanceOf(Node::class, $u);
        self::assertEquals(['User'], $u->labels()->toArray());
        self::assertEquals($email, $u->properties()['email']);
        self::assertEquals($uuid, $u->properties()['uuid']);
        self::assertEquals($email, $u->email);
        self::assertEquals($uuid, $u->uuid);
        self::assertEquals(
            json_encode([
                'id' => $u->id(),
                'labels' => $u->labels()->jsonSerialize(),
                'properties' => $u->properties()->jsonSerialize(),
            ], JSON_THROW_ON_ERROR),
            json_encode($u, JSON_THROW_ON_ERROR));

        /** @var Node $p */
        $p = $results[0]['p'];
        self::assertInstanceOf(Node::class, $p);
        self::assertEquals(['Food', 'Pizza'], $p->labels()->toArray());
        self::assertEquals($type, $p->properties()['type']);
        self::assertEquals(
            json_encode([
                'id' => $p->id(),
                'labels' => $p->labels()->jsonSerialize(),
                'properties' => $p->properties()->jsonSerialize(),
            ], JSON_THROW_ON_ERROR),
            json_encode($p, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @dataProvider connectionAliases
     *
     * @throws JsonException
     * @throws JsonException
     */
    public function testRelationship(string $alias): void
    {
        $result = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            $tsx->run('MATCH (n) DETACH DELETE n');

            return $tsx->run('MERGE (x:X {x: 1}) - [xy:XY {x: 1, y: 1}] -> (y:Y {y: 1}) RETURN xy')->first()->get('xy');
        }, $alias);

        self::assertInstanceOf(Relationship::class, $result);
        self::assertEquals('XY', $result->getType());
        self::assertEquals(['x' => 1, 'y' => 1], $result->getProperties()->toArray());
        self::assertEquals(1, $result->x);
        self::assertEquals(1, $result->y);
        self::assertEquals(
            json_encode([
                'id' => $result->getId(),
                'type' => $result->getType(),
                'startNodeId' => $result->getStartNodeId(),
                'endNodeId' => $result->getEndNodeId(),
                'properties' => $result->getProperties(),
            ], JSON_THROW_ON_ERROR),
            json_encode($result, JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testPath(string $alias): void
    {
        $results = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run(<<<'CYPHER'
MERGE (b:Node {x:$x}) - [:HasNode {attribute: $xy}] -> (:Node {y:$y}) - [:HasNode {attribute: $yz}] -> (:Node {z:$z})
WITH b
MATCH (x:Node) - [y:HasNode*2] -> (z:Node)
RETURN x, y, z
CYPHER, ['x' => 'x', 'xy' => 'xy', 'y' => 'y', 'yz' => 'yz', 'z' => 'z']);
        }, $alias);

        self::assertEquals(1, $results->count());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testPath2(string $alias): void
    {
        $results = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run(<<<'CYPHER'
CREATE path = ((a:Node {x:$x}) - [b:HasNode {attribute: $xy}] -> (c:Node {y:$y}) - [d:HasNode {attribute: $yz}] -> (e:Node {z:$z}))
RETURN path
CYPHER, ['x' => 'x', 'xy' => 'xy', 'y' => 'y', 'yz' => 'yz', 'z' => 'z']);
        }, $alias);

        self::assertEquals(1, $results->count());
        $path = $results->first()->get('path');

        self::assertInstanceOf(Path::class, $path);
        self::assertCount(2, $path->getRelationships());
        self::assertCount(3, $path->getNodes());

        self::assertEquals(['x' => 'x'], $path->getNodes()->get(0)->getProperties()->toArray());
        self::assertEquals(['y' => 'y'], $path->getNodes()->get(1)->getProperties()->toArray());
        self::assertEquals(['z' => 'z'], $path->getNodes()->get(2)->getProperties()->toArray());
        self::assertEquals(['attribute' => 'xy'], $path->getRelationships()->get(0)->getProperties()->toArray());
        self::assertEquals(['attribute' => 'yz'], $path->getRelationships()->get(1)->getProperties()->toArray());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testPathMultiple(string $alias): void
    {
        $result = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            $tsx->run('MATCH (x) DETACH DELETE (x)');
            $tsx->run('CREATE (:Node) - [:HasNode] -> (:Node)');
            $tsx->run('CREATE (:Node) - [:HasNode] -> (:Node)');

            return $tsx->run('RETURN (:Node) - [:HasNode] -> (:Node) as paths');
        }, $alias);

        self::assertCount(1, $result);
        $paths = $result->first()->get('paths');
        self::assertInstanceOf(CypherList::class, $paths);
        self::assertCount(2, $paths);
        foreach ($paths as $path) {
            self::assertInstanceOf(Path::class, $path);
        }
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testPropertyTypes(string $alias): void
    {
        $result = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run(<<<CYPHER
WITH
    point({x: 3, y: 4}) AS p,
    range(5, 15) AS l,
    date("2019-06-01") AS d,
    datetime("2019-06-01T18:40:32.142+0100") AS dt,
    duration({days: 14, hours:16, minutes: 12}) AS du,
    localdatetime() AS ldt,
    localtime("12") AS lt,
    time("12:00:00.000000000") AS t
MERGE (a:AllInOne {
    thePoint: p,
    theList: l,
    theDate: d,
    theDateTime: dt,
    theDuration: du,
    theLocalDateTime: ldt,
    theLocalTime: lt,
    theTime: t
})

RETURN a
CYPHER
            );
        }, $alias);

        $node = $result->first()->get('a');

        if (str_contains($alias, 'http')) {
            self::markTestSkipped('Http does not support nested properties');
        } else {
            self::assertInstanceOf(Node::class, $node);
            self::assertInstanceOf(PointInterface::class, $node->thePoint);
            self::assertInstanceOf(CypherList::class, $node->theList);
            self::assertInstanceOf(Date::class, $node->theDate);
            self::assertInstanceOf(DateTime::class, $node->theDateTime);
            self::assertInstanceOf(Duration::class, $node->theDuration);
            self::assertInstanceOf(LocalDateTime::class, $node->theLocalDateTime);
            self::assertInstanceOf(LocalTime::class, $node->theLocalTime);
            self::assertInstanceOf(Time::class, $node->theTime);
        }
    }

    private function articlesQuery(): string
    {
        return <<<CYPHER
UNWIND [
    { title: 'Cypher Basics I',
      created: datetime('2019-06-01T18:40:32.142+0100'),
      datePublished: date('2019-06-01'),
      readingTime: {minutes: 2, seconds: 15} },
    { title: 'Cypher Basics II',
      created: datetime('2019-06-02T10:23:32.122+0100'),
      datePublished: date('2019-06-02'),
      readingTime: {minutes: 2, seconds: 30} },
    { title: 'Dates, Datetimes, and Durations in Neo4j',
      created: datetime(),
      datePublished: date('2021-04-25'),
      readingTime: {minutes: 3, seconds: 30} }
] AS articleProperties

CREATE (article:Article {title: articleProperties.title})
SET article.created = articleProperties.created,
    article.datePublished = articleProperties.datePublished,
    article.readingTime = duration(articleProperties.readingTime)
CYPHER;
    }
}
