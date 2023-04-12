<?php

/** @noinspection PhpUndefinedFieldInspection */

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Tests\Integration;

use function compact;

use DateInterval;

use function json_encode;

use Laudis\Neo4j\Contracts\PointInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
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

/**
 * @psalm-suppress MixedArrayAccess
 */
final class OGMFormatterIntegrationTest extends EnvironmentAwareIntegrationTest
{
    public function testNull(): void
    {
        $results = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN null as x'));

        self::assertNull($results->first()->get('x'));
    }

    public function testList(): void
    {
        $results = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN range(5, 15) as list, range(16, 35) as list2'));

        $list = $results->first()->get('list');
        $list2 = $results->first()->get('list2');

        self::assertInstanceOf(CypherList::class, $list);
        self::assertInstanceOf(CypherList::class, $list2);
        self::assertEquals(range(5, 15), $list->toArray());
        self::assertEquals(range(16, 35), $list2->toArray());
        self::assertEquals(json_encode(range(5, 15), JSON_THROW_ON_ERROR), json_encode($list, JSON_THROW_ON_ERROR));
        self::assertEquals(json_encode(range(16, 35), JSON_THROW_ON_ERROR), json_encode($list2, JSON_THROW_ON_ERROR));
    }

    public function testMap(): void
    {
        $map = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN {a: "b", c: "d"} as map')->first()->get('map'));
        self::assertInstanceOf(CypherMap::class, $map);
        self::assertEquals(['a' => 'b', 'c' => 'd'], $map->toArray());
        self::assertEquals(json_encode(['a' => 'b', 'c' => 'd'], JSON_THROW_ON_ERROR), json_encode($map, JSON_THROW_ON_ERROR));
    }

    public function testBoolean(): void
    {
        $results = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN true as bool1, false as bool2'));

        self::assertEquals(1, $results->count());
        self::assertIsBool($results->first()->get('bool1'));
        self::assertIsBool($results->first()->get('bool2'));
    }

    public function testInteger(): void
    {
        $results = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run(<<<CYPHER
UNWIND [{num: 1}, {num: 2}, {num: 3}] AS x
RETURN x.num
ORDER BY x.num ASC
CYPHER
        ));

        self::assertEquals(3, $results->count());
        self::assertEquals(1, $results[0]['x.num']);
        self::assertEquals(2, $results[1]['x.num']);
        self::assertEquals(3, $results[2]['x.num']);
    }

    public function testFloat(): void
    {
        $results = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN 0.1 AS float'));

        self::assertIsFloat($results->first()->get('float'));
    }

    public function testString(): void
    {
        $results = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN "abc" AS string'));

        self::assertIsString($results->first()->get('string'));
    }

    public function testDate(): void
    {
        $results = $this->getSession()->transaction(function (TransactionInterface $tsx) {
            $query = $this->articlesQuery();
            $query .= 'RETURN article.datePublished as published_at';

            return $tsx->run($query);
        });

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

    public function testTime(): void
    {
        $results = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN time("12:00:00.000000000") AS time'));

        $time = $results->first()->get('time');
        self::assertInstanceOf(Time::class, $time);
        self::assertEquals(12.0 * 60 * 60 * 1_000_000_000, $time->getNanoSeconds());
        self::assertEquals(12.0 * 60 * 60 * 1_000_000_000, $time->nanoSeconds);
    }

    public function testLocalTime(): void
    {
        $results = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN localtime("12") AS time'));

        /** @var LocalTime $time */
        $time = $results->first()->get('time');
        self::assertInstanceOf(LocalTime::class, $time);
        self::assertEquals(43_200_000_000_000, $time->getNanoseconds());

        $results = $this->getSession()->run('RETURN localtime("09:23:42.000") AS time', []);

        /** @var LocalTime $time */
        $time = $results->first()->get('time');
        self::assertInstanceOf(LocalTime::class, $time);
        self::assertEquals(33_822_000_000_000, $time->getNanoseconds());
        self::assertEquals(33_822_000_000_000, $time->nanoseconds);
    }

    public function testDateTime(): void
    {
        $results = $this->getSession()->transaction(function (TransactionInterface $tsx) {
            $query = $this->articlesQuery();
            $query .= 'RETURN article.created as created_at';

            return $tsx->run($query);
        });

        self::assertEquals(3, $results->count());

        $createdAt = $results[0]['created_at'];
        self::assertInstanceOf(DateTime::class, $createdAt);
        if ($createdAt->isLegacy()) {
            self::assertEquals(1_559_414_432, $createdAt->getSeconds());
        } else {
            self::assertEquals(1_559_410_832, $createdAt->getSeconds());
        }

        self::assertEquals(142_000_000, $createdAt->getNanoseconds());
        self::assertEquals(3600, $createdAt->getTimeZoneOffsetSeconds());
        self::assertEquals(142_000_000, $createdAt->getNanoseconds());
        self::assertEquals(3600, $createdAt->getTimeZoneOffsetSeconds());

        if ($createdAt->isLegacy()) {
            self::assertEquals('{"seconds":1559414432,"nanoseconds":142000000,"tzOffsetSeconds":3600}', json_encode($createdAt, JSON_THROW_ON_ERROR));
        } else {
            self::assertEquals('{"seconds":1559410832,"nanoseconds":142000000,"tzOffsetSeconds":3600}', json_encode($createdAt, JSON_THROW_ON_ERROR));
        }
    }

    public function testLocalDateTime(): void
    {
        $result = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN localdatetime() as local')->first()->get('local'));

        self::assertInstanceOf(LocalDateTime::class, $result);
        $date = $result->toDateTime();
        self::assertEquals($result->getSeconds(), $date->getTimestamp());
    }

    public function testDuration(): void
    {
        $results = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run(<<<CYPHER
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
        ));

        self::assertEquals(6, $results->count());
        self::assertEquals(new Duration(0, 14, 58320, 0), $results[0]['aDuration']);
        $duration = $results[1]['aDuration'];
        self::assertInstanceOf(Duration::class, $duration);
        self::assertEquals(new Duration(5, 1, 43200, 0), $duration);
        self::assertEquals(5, $duration->getMonths());
        self::assertEquals(1, $duration->getDays());
        self::assertEquals(43200, $duration->getSeconds());
        self::assertEquals(0, $duration->getNanoseconds());
        self::assertEquals(new Duration(0, 22, 71509, 500_000_000), $results[2]['aDuration']);
        self::assertEquals(new Duration(0, 17, 43200, 0), $results[3]['aDuration']);
        self::assertEquals(new Duration(0, 0, 91, 123_456_789), $results[4]['aDuration']);
        self::assertEquals(new Duration(0, 0, 91, 123_456_789), $results[5]['aDuration']);

        self::assertEquals(5, $duration->getMonths());
        self::assertEquals(1, $duration->getDays());
        self::assertEquals(43200, $duration->getSeconds());
        self::assertEquals(0, $duration->getNanoseconds());
        $interval = new DateInterval(sprintf('P%dM%dDT%dS', 5, 1, 43200));
        self::assertEquals($interval, $duration->toDateInterval());
        self::assertEquals('{"months":5,"days":1,"seconds":43200,"nanoseconds":0}', json_encode($duration, JSON_THROW_ON_ERROR));
    }

    public function testPoint(): void
    {
        $result = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN point({x: 3, y: 4}) AS point'));
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

    public function testNode(): void
    {
        $uuid = 'cc60fd69-a92b-47f3-9674-2f27f3437d66';
        $email = 'a@b.c';
        $type = 'pepperoni';

        $arguments = compact('email', 'uuid', 'type');

        $results = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run(
            'MERGE (u:User{email: $email})-[:LIKES]->(p:Food:Pizza {type: $type}) ON CREATE SET u.uuid=$uuid RETURN u, p',
            $arguments
        ));

        self::assertEquals(1, $results->count());

        /** @var Node $u */
        $u = $results[0]['u'];
        self::assertInstanceOf(Node::class, $u);
        self::assertEquals(['User'], $u->getLabels()->toArray());
        self::assertEquals($email, $u->getProperties()['email']);
        self::assertEquals($uuid, $u->getProperties()['uuid']);
        self::assertEquals($email, $u->email);
        self::assertEquals($uuid, $u->uuid);
        self::assertEquals(
            json_encode([
                'id' => $u->getId(),
                'labels' => $u->getLabels()->jsonSerialize(),
                'properties' => $u->getProperties()->jsonSerialize(),
            ], JSON_THROW_ON_ERROR),
            json_encode($u, JSON_THROW_ON_ERROR));

        /** @var Node $p */
        $p = $results[0]['p'];
        self::assertInstanceOf(Node::class, $p);
        self::assertEquals(['Food', 'Pizza'], $p->getLabels()->toArray());
        self::assertEquals($type, $p->getProperties()['type']);
        self::assertEquals(
            json_encode([
                'id' => $p->getId(),
                'labels' => $p->getLabels()->jsonSerialize(),
                'properties' => $p->getProperties()->jsonSerialize(),
            ], JSON_THROW_ON_ERROR),
            json_encode($p, JSON_THROW_ON_ERROR)
        );
    }

    public function testRelationship(): void
    {
        $result = $this->getSession()->transaction(static function (TransactionInterface $tsx) {
            $tsx->run('MATCH (n) DETACH DELETE n');

            return $tsx->run('MERGE (x:X {x: 1}) - [xy:XY {x: 1, y: 1}] -> (y:Y {y: 1}) RETURN xy')->first()->get('xy');
        });

        self::assertInstanceOf(Relationship::class, $result);
        self::assertEquals('XY', $result->getType());
        self::assertEquals(['x' => 1, 'y' => 1], $result->getProperties()->toArray());
        self::assertEquals(1, $result->x);
        self::assertEquals(1, $result->y);
        self::assertEquals(
            json_encode([
                'id' => $result->getId(),
                'type' => $result->getType(),
                'properties' => $result->getProperties(),
                'startNodeId' => $result->getStartNodeId(),
                'endNodeId' => $result->getEndNodeId(),
            ], JSON_THROW_ON_ERROR),
            json_encode($result, JSON_THROW_ON_ERROR)
        );
    }

    public function testPath(): void
    {
        $results = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run(<<<'CYPHER'
MERGE (b:Node {x:$x}) - [:HasNode {attribute: $xy}] -> (:Node {y:$y}) - [:HasNode {attribute: $yz}] -> (:Node {z:$z})
WITH b
MATCH (x:Node) - [y:HasNode*2] -> (z:Node)
RETURN x, y, z
CYPHER, ['x' => 'x', 'xy' => 'xy', 'y' => 'y', 'yz' => 'yz', 'z' => 'z']));

        self::assertEquals(1, $results->count());
    }

    public function testPath2(): void
    {
        $results = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run(<<<'CYPHER'
CREATE path = ((a:Node {x:$x}) - [b:HasNode {attribute: $xy}] -> (c:Node {y:$y}) - [d:HasNode {attribute: $yz}] -> (e:Node {z:$z}))
RETURN path
CYPHER, ['x' => 'x', 'xy' => 'xy', 'y' => 'y', 'yz' => 'yz', 'z' => 'z']));

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

    public function testPropertyTypes(): void
    {
        $result = $this->getSession(['neo4j', 'bolt'])->transaction(static fn (TransactionInterface $tsx) => $tsx->run(<<<CYPHER
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
        ));

        $node = $result->first()->get('a');

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
