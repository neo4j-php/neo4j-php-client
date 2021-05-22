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

use Ds\Map;
use Ds\Vector;
use DateInterval;
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\Types\Date;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Types\Duration;
use Laudis\Neo4j\Types\DateTime;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Formatter\OGMFormatter;
use PHPUnit\Framework\TestCase;

final class OGMFormatterIntegrationTest extends TestCase
{
    /** @var ClientInterface<Vector<Map<string, mixed>>> */
    private ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = ClientBuilder::create()
            ->withDriver('bolt', 'bolt://neo4j:test@neo4j')
            ->withDriver('http', 'http://neo4j:test@neo4j')
            ->withDriver('cluster', 'neo4j://neo4j:test@core1')
            ->withFormatter(new OGMFormatter())
            ->build();
    }

    /**
     * @dataProvider transactionProvider
     */
    public function testPath(string $alias): void
    {
        $results = $this->client->run(<<<'CYPHER'
MERGE (b:Node {x:$x}) - [:HasNode {attribute: $xy}] -> (:Node {y:$y}) - [:HasNode {attribute: $yz}] -> (:Node {z:$z})
WITH b
MATCH (x:Node) - [y:HasNode*2] -> (z:Node)
RETURN x, y, z
CYPHER
            , ['x' => 'x', 'xy' => 'xy', 'y' => 'y', 'yz' => 'yz', 'z' => 'z'], $alias);

        self::assertEquals(1, $results->count());
    }

    /**
     * @dataProvider transactionProvider
     */
    public function testNode(string $alias): void
    {
        $uuid = 'cc60fd69-a92b-47f3-9674-2f27f3437d66';
        $email = 'a@b.c';
        $type = 'pepperoni';

        $results = $this->client->run(
            'MERGE (u:User{email: $email})-[:LIKES]->(p:Food:Pizza {type: $type}) ON CREATE SET u.uuid=$uuid RETURN u, p',
            ['email' => $email, 'uuid' => $uuid, 'type' => $type], $alias
        );

        self::assertEquals(1, $results->count());

        /** @var Node $u */
        $u = $results[0]['u'];
        self::assertInstanceOf(Node::class, $u);
        self::assertEquals(new Vector(['User']), $u->labels());
        self::assertEquals($email, $u->properties()['email']);
        self::assertEquals($uuid, $u->properties()['uuid']);

        /** @var Node $p */
        $p = $results[0]['p'];
        self::assertInstanceOf(Node::class, $p);
        self::assertEquals(new Vector(['Food', 'Pizza']), $p->labels());
        self::assertEquals($type, $p->properties()['type']);
    }

    /**
     * @dataProvider transactionProvider
     */
    public function testInteger(string $alias): void
    {
        $this->client->run('MATCH (n) DETACH DELETE n');
        $results = $this->client->run(<<<CYPHER
CREATE (z:Int {num:1}), (:Int {num: 2}), (:Int {num:3})
WITH z
MATCH (x:Int)
RETURN x.num ORDER BY x.num ASC
CYPHER);

        self::assertCount(3, $results);
        self::assertEquals(1, $results[0]['x.num']);
        self::assertEquals(2, $results[1]['x.num']);
        self::assertEquals(3, $results[2]['x.num']);
    }

    /**
     * @dataProvider transactionProvider
     */
    public function testDuration(string $alias): void
    {
        $results = $this->client->run(<<<CYPHER
UNWIND [
  duration({days: 14, hours:16, minutes: 12}),
  duration({months: 5, days: 1.5}),
  duration({months: 0.75}),
  duration({weeks: 2.5}),
  duration({minutes: 1.5, seconds: 1, milliseconds: 123, microseconds: 456, nanoseconds: 789}),
  duration({minutes: 1.5, seconds: 1, nanoseconds: 123456789})
] AS aDuration
RETURN aDuration
CYPHER);

        self::assertCount(6, $results);
        self::assertEquals(new Duration(0, 14, 58320, 0), $results[0]['aDuration']);
        self::assertEquals(new Duration(5, 1, 43200, 0), $results[1]['aDuration']);
        self::assertEquals(new Duration(0, 22, 71509, 500000000), $results[2]['aDuration']);
        self::assertEquals(new Duration(0, 17, 43200, 0), $results[3]['aDuration']);
        self::assertEquals(new Duration(0, 0, 91, 123456789), $results[4]['aDuration']);
        self::assertEquals(new Duration(0, 0, 91, 123456789), $results[5]['aDuration']);

        self::assertEquals(5, $results[1]['aDuration']->months());
        self::assertEquals(1, $results[1]['aDuration']->days());
        self::assertEquals(43200, $results[1]['aDuration']->seconds());
        self::assertEquals(0, $results[1]['aDuration']->nanoseconds());
        $interval = new DateInterval(sprintf('P%dM%dDT%dS', 5, 1, 43200));
        self::assertEquals($interval, $results[1]['aDuration']->toDateInterval());
    }

    /**
     * @dataProvider transactionProvider
     */
    public function testDate(string $alias): void
    {
        $query = $this->articlesQuery();
        $query .= 'RETURN article.datePublished as published_at';

        $results = $this->client->run($query);

        self::assertCount(3, $results);

        self::assertInstanceOf(Date::class, $results[0]['published_at']);
        self::assertEquals(18048, $results[0]['published_at']->days());

        self::assertInstanceOf(Date::class, $results[1]['published_at']);
        self::assertEquals(18049, $results[1]['published_at']->days());

        self::assertInstanceOf(Date::class, $results[2]['published_at']);
        self::assertEquals(18742, $results[2]['published_at']->days());
    }

    /**
     * @dataProvider transactionProvider
     */
    public function testDateTime(string $alias): void
    {
        $query = $this->articlesQuery();
        $query .= 'RETURN article.created as created_at';

        $results = $this->client->run($query);

        self::assertCount(3, $results);

        self::assertInstanceOf(DateTime::class, $results[0]['created_at']);
        self::assertEquals(1559414432, $results[0]['created_at']->seconds());
        self::assertEquals(142000000, $results[0]['created_at']->nanoseconds());
        self::assertEquals(3600, $results[0]['created_at']->offsetSeconds());

        self::assertInstanceOf(DateTime::class, $results[1]['created_at']);
        self::assertEquals(1559471012, $results[1]['created_at']->seconds());
        self::assertEquals(122000000, $results[1]['created_at']->nanoseconds());
        self::assertEquals(3600, $results[1]['created_at']->offsetSeconds());

        self::assertInstanceOf(DateTime::class, $results[2]['created_at']);
        self::assertGreaterThan(0, $results[2]['created_at']->seconds());
        self::assertGreaterThan(0, $results[2]['created_at']->nanoseconds());
        self::assertEquals(0, $results[2]['created_at']->offsetSeconds());
    }

    /**
     * @dataProvider transactionProvider
     */
    public function testDatesAsNodeProperties(string $alias): void
    {
        $query = $this->articlesQuery();
        $query .= 'RETURN article';

        $results = $this->client->run($query);

        self::assertCount(3, $results);

        foreach ($results as $result) {
            self::assertInstanceOf(DateTime::class, $result['article']->created);
            self::assertInstanceOf(Date::class, $result['article']->datePublished);
            self::assertInstanceOf(Duration::class, $result['article']->readingTime);
        }
    }

    private function articlesQuery(): string
    {
        return <<<CYPHER
UNWIND [
    { title: "Cypher Basics I",
      created: datetime("2019-06-01T18:40:32.142+0100"),
      datePublished: date("2019-06-01"),
      readingTime: {minutes: 2, seconds: 15} },
    { title: "Cypher Basics II",
      created: datetime("2019-06-02T10:23:32.122+0100"),
      datePublished: date("2019-06-02"),
      readingTime: {minutes: 2, seconds: 30} },
    { title: "Dates, Datetimes, and Durations in Neo4j",
      created: datetime(),
      datePublished: date("2021-04-25"),
      readingTime: {minutes: 3, seconds: 30} }
] AS articleProperties

CREATE (article:Article {title: articleProperties.title})
SET article.created = articleProperties.created,
    article.datePublished = articleProperties.datePublished,
    article.readingTime = duration(articleProperties.readingTime)
CYPHER;

    }

    /**
     * @return array<int, array<int, string>>
     */
    public function transactionProvider(): array
    {
        return [
            ['http'],
            ['bolt'],
            ['cluster'],
        ];
    }
}
