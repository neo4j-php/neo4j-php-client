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

namespace Laudis\Neo4j\Tests\Integration;

use function bin2hex;

use Bolt\error\PackException;
use DateInterval;
use DateTimeImmutable;

use function dump;

use Laudis\Neo4j\Contracts\PointInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\SummaryCounters;
use Laudis\Neo4j\Enum\VectorTypeMarker;
use Laudis\Neo4j\Formatter\Specialised\BoltOGMTranslator;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Tests\EnvironmentAwareIntegrationTest;
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
use Laudis\Neo4j\Types\Vector;

use function random_bytes;
use function serialize;
use function unserialize;

final class SummarizedResultFormatterTest extends EnvironmentAwareIntegrationTest
{
    public function testAcceptanceRead(): void
    {
        $result = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN 1 AS one'));
        self::assertInstanceOf(SummarizedResult::class, $result);
        self::assertEquals(1, $result->first()->get('one'));
    }

    public function testAcceptanceWrite(): void
    {
        $counters = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('CREATE (x:X {y: $x}) RETURN x', ['x' => bin2hex(random_bytes(128))]))->getSummary()->getCounters();
        self::assertEquals(new SummaryCounters(1, 0, 0, 0, 1, 1, 0, 0, 0, 0, 0, true), $counters);
    }

    public function testGetResults(): void
    {
        $results = $this->getSession()->run('RETURN 1 AS one', [])->getResults();

        self::assertNotInstanceOf(SummarizedResult::class, $results);
        self::assertInstanceOf(CypherList::class, $results);

        $jsonSerialize = $results->jsonSerialize();
        self::assertIsArray($jsonSerialize);
        self::assertArrayNotHasKey('summary', $jsonSerialize);
        self::assertArrayNotHasKey('result', $jsonSerialize);

        $first = $results->first();
        self::assertInstanceOf(CypherMap::class, $first);
        self::assertEquals(1, $first->get('one'));
    }

    public function testSerialize(): void
    {
        $results = $this->getSession()->run('RETURN 1 AS one', []);

        $serialise = serialize($results);
        $resultHasBeenSerialized = unserialize($serialise);

        self::assertInstanceOf(SummarizedResult::class, $resultHasBeenSerialized);
        self::assertEquals($results->toRecursiveArray(), $resultHasBeenSerialized->toRecursiveArray());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDump(): void
    {
        $results = $this->getSession()->run('RETURN 1 AS one', []);

        dump($results);
    }

    public function testConsumedPositive(): void
    {
        $results = $this->getSession()->run('CALL apoc.util.sleep(1000)  RETURN 1 AS one');

        self::assertInstanceOf(SummarizedResult::class, $results);

        self::assertGreaterThan(100, $results->getSummary()->getResultConsumedAfter());
    }

    public function testDateTime(): void
    {
        if (str_starts_with($_ENV['CONNECTION'] ?? '', 'http')) {
            $this->markTestSkipped('http does not support datetime conversion');
        }

        $dt = new DateTimeImmutable();
        $ls = $this->getSession()->run('RETURN $x AS x', ['x' => $dt])->first()->get('x');

        $this->assertInstanceOf(DateTimeZoneId::class, $ls);
        $this->assertEquals($dt, $ls->toDateTime());
    }

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

    /**
     * Cypher cannot create vectors (no vector() function, no vector literal). Vectors are transported
     * by Bolt. This test sends a Vector as a parameter and asserts the round-trip via the formatter.
     *
     * @return iterable<string, array{values: list<int|float>, typeMarker: VectorTypeMarker|null, useDelta: bool}>
     */
    public static function vectorAsParameterProvider(): iterable
    {
        yield 'float vector (FLOAT_64)' => [
            'values' => [0.1, 0.2, 0.3],
            'typeMarker' => VectorTypeMarker::FLOAT_64,
            'useDelta' => true,
        ];
        yield 'float vector (FLOAT_32)' => [
            'values' => [0.1, 0.2, 0.3],
            'typeMarker' => VectorTypeMarker::FLOAT_32,
            'useDelta' => true,
        ];
        yield 'integer vector (INT_64)' => [
            'values' => [1, 2, 3],
            'typeMarker' => VectorTypeMarker::INT_64,
            'useDelta' => false,
        ];
        yield 'integer vector (INT_32)' => [
            'values' => [1, 2, 3],
            'typeMarker' => VectorTypeMarker::INT_32,
            'useDelta' => false,
        ];
    }

    /**
     * Vector round-trip via parameter: driver encodes Vector with Bolt, Neo4j echoes it, formatter maps to Vector.
     * Skipped when the negotiated Bolt protocol does not support Vector as parameter (Vector is Bolt 6 only).
     *
     * @dataProvider vectorAsParameterProvider
     *
     * @param list<int|float> $values
     */
    public function testVectorAsReturnValue(array $values, ?VectorTypeMarker $typeMarker, bool $useDelta): void
    {
        $embeddingParam = new Vector($values, $typeMarker);
        try {
            $results = $this->getSession()->transaction(
                static fn (TransactionInterface $tsx) => $tsx->run('RETURN $embedding AS embedding', ['embedding' => $embeddingParam])
            );
        } catch (PackException $e) {
            if (str_contains($e->getMessage(), 'structure as parameter is not supported')) {
                self::markTestSkipped('Bolt protocol in use does not support Vector as parameter (Bolt 6 required).');
            }
            throw $e;
        }

        $row = $results->first();
        $embedding = $row->get('embedding');
        self::assertInstanceOf(Vector::class, $embedding);
        if ($useDelta) {
            self::assertEqualsWithDelta($values, $embedding->getValues(), 0.0001);
        } else {
            self::assertEquals($values, $embedding->getValues());
        }
    }

    public function testVectorAsParameterRoundTrip(): void
    {
        $vec = [0.1, 0.2, 0.3];
        $results = $this->getSession()->transaction(
            static fn (TransactionInterface $tsx) => $tsx->run('WITH $vec AS v RETURN v', ['vec' => $vec])
        );
        $row = $results->first();
        $v = $row->get('v');
        self::assertTrue($v instanceof CypherList || $v instanceof Vector);
        self::assertEquals($vec, $v instanceof Vector ? $v->getValues() : $v->toArray());
    }

    public function testMap(): void
    {
        $map = $this->getSession()->transaction(static fn (TransactionInterface $tsx): mixed => $tsx->run('RETURN {a: "b", c: "d"} as map')->first()->get('map'));
        self::assertInstanceOf(CypherMap::class, $map);
        $array = $map->toArray();
        ksort($array);
        self::assertEquals(['a' => 'b', 'c' => 'd'], $array);
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

    public function testDateTime2(): void
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
        $result = $this->getSession()->transaction(static fn (TransactionInterface $tsx): mixed => $tsx->run('RETURN localdatetime() as local')->first()->get('local'));

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
        $result = $this->getSession()->transaction(static function (TransactionInterface $tsx): mixed {
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
                'startNodeElementId' => $result->getStartNodeElementId(),
                'endNodeElementId' => $result->getEndNodeElementId(),
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
CREATE path = (a:Node {x:$x}) - [b:HasNode {attribute: $xy}] -> (c:Node {y:$y}) - [d:HasNode {attribute: $yz}] -> (e:Node {z:$z})
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

    public function testFormatBoltStatsWithFalseSystemUpdates(): void
    {
        $formatter = new SummarizedResultFormatter(new BoltOGMTranslator());

        $response = [
            'stats' => [
                'nodes-created' => 1,
                'nodes-deleted' => 0,
                'relationships-created' => 0,
                'relationships-deleted' => 0,
                'properties-set' => 2,
                'labels-added' => 1,
                'labels-removed' => 0,
                'indexes-added' => 0,
                'indexes-removed' => 0,
                'constraints-added' => 0,
                'constraints-removed' => 0,
                'contains-updates' => true,
                'contains-system-updates' => false,
                'system-updates' => 0,
            ],
        ];

        $counters = $formatter->formatBoltStats($response);

        self::assertInstanceOf(SummaryCounters::class, $counters);
        self::assertEquals(1, $counters->nodesCreated());
        self::assertEquals(2, $counters->propertiesSet());
        self::assertSame(0, $counters->systemUpdates());
    }

    public function testSystemUpdatesWithPotentialFalseValues(): void
    {
        $this->getSession()->run('CREATE INDEX duplicate_test_index IF NOT EXISTS FOR (n:TestSystemUpdates) ON (n.duplicateProperty)');
        $result = $this->getSession()->run('CREATE INDEX duplicate_test_index IF NOT EXISTS FOR (n:TestSystemUpdates) ON (n.duplicateProperty)');

        $summary = $result->getSummary();
        $counters = $summary->getCounters();

        $this->assertGreaterThanOrEqual(0, $counters->systemUpdates());
        $this->assertEquals($counters->systemUpdates() > 0, $counters->containsSystemUpdates());

        $result2 = $this->getSession()->run('DROP INDEX non_existent_test_index IF EXISTS');

        $summary2 = $result2->getSummary();
        $counters2 = $summary2->getCounters();

        $this->assertEquals(0, $counters2->systemUpdates());
        $this->assertFalse($counters2->containsSystemUpdates());

        $this->getSession()->run('DROP INDEX duplicate_test_index IF EXISTS');
    }

    public function testMultipleSystemOperationsForBug(): void
    {
        $operations = [
            'CREATE INDEX multi_test_1 IF NOT EXISTS FOR (n:MultiTestNode) ON (n.prop1)',
            'CREATE INDEX multi_test_2 IF NOT EXISTS FOR (n:MultiTestNode) ON (n.prop2)',
            'CREATE CONSTRAINT multi_test_constraint IF NOT EXISTS FOR (n:MultiTestNode) REQUIRE n.id IS UNIQUE',
            'DROP INDEX multi_test_1 IF EXISTS',
            'DROP INDEX multi_test_2 IF EXISTS',
            'DROP CONSTRAINT multi_test_constraint IF EXISTS',
        ];

        foreach ($operations as $operation) {
            $result = $this->getSession()->run($operation);

            $summary = $result->getSummary();
            $counters = $summary->getCounters();

            // Test that system operations properly track system updates
            $this->assertGreaterThanOrEqual(0, $counters->systemUpdates());
            $this->assertEquals($counters->systemUpdates() > 0, $counters->containsSystemUpdates());
        }
    }

    public function testRegularDataOperationsStillWork(): void
    {
        $result = $this->getSession()->run('CREATE (n:RegularTestNode {name: "test", id: $id}) RETURN n', ['id' => bin2hex(random_bytes(8))]);

        $summary = $result->getSummary();
        $counters = $summary->getCounters();

        $this->assertEquals(0, $counters->systemUpdates());
        $this->assertFalse($counters->containsSystemUpdates());

        $this->getSession()->run('MATCH (n:RegularTestNode) DELETE n');
    }
}
