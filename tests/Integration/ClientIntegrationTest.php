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

use function count;
use InvalidArgumentException;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Basic\Driver;
use Laudis\Neo4j\Bolt\BoltDriver;
use Laudis\Neo4j\Bolt\ConnectionPool;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use ReflectionClass;
use function str_starts_with;

/**
 * @psalm-import-type OGMTypes from OGMFormatter
 *
 * @extends EnvironmentAwareIntegrationTest<CypherList<CypherMap<OGMTypes>>>
 */
final class ClientIntegrationTest extends EnvironmentAwareIntegrationTest
{
    protected static function formatter(): FormatterInterface
    {
        return OGMFormatter::create();
    }

    public function testDifferentAuth(): void
    {
        foreach (self::buildConnections() as $connection) {
            $uri = Uri::create($connection);

            $auth = Authenticate::fromUrl($uri);
            $uri = $uri->withUserInfo('');

            $driver = Driver::create($uri, null, $auth);
            self::assertTrue($driver->verifyConnectivity());

            self::assertEquals(1, $driver->createSession()->run('RETURN 1 AS one')->first()->get('one'));
        }
    }

    public function testEqualEffect(): void
    {
        if (count(self::connectionAliases()) === 1) {
            self::markTestSkipped('Only one connection alias provided. Comparison is impossible.');
        }
        $statement = new Statement(
            'merge(u:User{email: $email}) on create set u.uuid=$uuid return u',
            ['email' => 'a@b.c', 'uuid' => 'cc60fd69-a92b-47f3-9674-2f27f3437d66']
        );

        $prev = null;
        foreach (self::connectionAliases() as $current) {
            if (str_starts_with($current[0], 'neo4j')) {
                self::markTestSkipped('Cannot guarantee successful test in cluster');
            }
            if ($prev !== null) {
                $x = $this->getClient()->runStatement($statement, $prev);
                $y = $this->getClient()->runStatement($statement, $current[0]);

                self::assertEquals($x->first()->getAsNode('u')->getProperties()->toArray(), $y->first()->getAsNode('u')->getProperties()->toArray());
            }
            $prev = $current[0];
        }

        self::assertTrue(true);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testAvailabilityFullImplementation(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $results = $this->getClient()->getDriver($alias)
            ->createSession()
            ->beginTransaction()
            ->run('UNWIND [1] AS x RETURN x')
            ->first()
            ->get('x');

        self::assertEquals(1, $results);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testTransactionFunction(string $alias): void
    {
        $result = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run('UNWIND [1] AS x RETURN x')->first()->getAsInt('x');
        }, $alias);

        self::assertEquals(1, $result);

        $result = $this->getClient()->readTransaction(static function (TransactionInterface $tsx) {
            return $tsx->run('UNWIND [1] AS x RETURN x')->first()->getAsInt('x');
        }, $alias);

        self::assertEquals(1, $result);

        $result = $this->getClient()->writeTransaction(static function (TransactionInterface $tsx) {
            return $tsx->run('UNWIND [1] AS x RETURN x')->first()->getAsInt('x');
        }, $alias);

        self::assertEquals(1, $result);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testValidRun(string $alias): void
    {
        $response = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run(<<<'CYPHER'
MERGE (x:TestNode {test: $test})
WITH x
MERGE (y:OtherTestNode {test: $otherTest})
WITH x, y, {c: 'd'} AS map, [1, 2, 3] AS list
RETURN x, y, x.test AS test, map, list
CYPHER, ['test' => 'a', 'otherTest' => 'b']);
        }, $alias);

        self::assertEquals(1, $response->count());
        $map = $response->first();
        self::assertEquals(5, $map->count());
        self::assertEquals(['test' => 'a'], $map->getAsNode('x')->getProperties()->toArray());
        self::assertEquals(['test' => 'b'], $map->getAsNode('y')->getProperties()->toArray());
        self::assertEquals('a', $map->get('test'));
        self::assertEquals(['c' => 'd'], $map->getAsMap('map')->toArray());
        self::assertEquals([1, 2, 3], $map->getAsArrayList('list')->toArray());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testInvalidRun(string $alias): void
    {
        $exception = false;
        try {
            $this->getClient()->transaction(static function (TransactionInterface $tsx) {
                return $tsx->run('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b']);
            }, $alias);
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testInvalidRunRetry(string $alias): void
    {
        $exception = false;
        try {
            $this->getClient()->run('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b'], $alias);
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);

        $this->getClient()->run('RETURN 1 AS one');
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testValidStatement(string $alias): void
    {
        $response = $this->getClient()->transaction(static function (TransactionInterface $tsx) {
            return $tsx->runStatement(Statement::create(<<<'CYPHER'
MERGE (x:TestNode {test: $test})
WITH x
MERGE (y:OtherTestNode {test: $otherTest})
WITH x, y, {c: 'd'} AS map, [1, 2, 3] AS list
RETURN x, y, x.test AS test, map, list
CYPHER, ['test' => 'a', 'otherTest' => 'b']));
        }, $alias);

        self::assertEquals(1, $response->count());
        $map = $response->first();
        self::assertEquals(5, $map->count());
        self::assertEquals(['test' => 'a'], $map->getAsNode('x')->getProperties()->toArray());
        self::assertEquals(['test' => 'b'], $map->getAsNode('y')->getProperties()->toArray());
        self::assertEquals('a', $map->get('test'));
        self::assertEquals(['c' => 'd'], $map->getAsMap('map')->toArray());
        self::assertEquals([1, 2, 3], $map->getAsArrayList('list')->toArray());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testInvalidStatement(string $alias): void
    {
        $exception = false;
        try {
            $statement = Statement::create('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b']);
            $this->getClient()->transaction(static fn (TransactionInterface $tsx) => $tsx->runStatement($statement), $alias);
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testStatements(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $params = ['test' => 'a', 'otherTest' => 'b'];
        $response = $this->getClient()->runStatements([
            Statement::create('MERGE (x:TestNode {test: $test})', $params),
            Statement::create('MERGE (x:OtherTestNode {test: $otherTest})', $params),
            Statement::create('RETURN 1 AS x', []),
        ],
            $alias
        );

        self::assertEquals(3, $response->count());
        self::assertEquals(0, $response->get(0)->count());
        self::assertEquals(0, $response->get(1)->count());
        self::assertEquals(1, $response->get(2)->count());
        self::assertEquals(1, $response->get(2)->first()->get('x'));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testInvalidStatements(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $this->expectException(Neo4jException::class);
        $params = ['test' => 'a', 'otherTest' => 'b'];
        $this->getClient()->runStatements([
            Statement::create('MERGE (x:TestNode {test: $test})', $params),
            Statement::create('MERGE (x:OtherTestNode {test: $otherTest})', $params),
            Statement::create('1 AS x;erns', []),
        ], $alias);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testMultipleTransactions(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $x = $this->getClient()->beginTransaction(null, $alias);
        $y = $this->getClient()->beginTransaction(null, $alias);
        self::assertNotSame($x, $y);
        $x->rollback();
        $y->rollback();
    }

    public function testInvalidConnection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot find a driver setup with alias: "gh"');

        $this->getClient()->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN 1 AS x'), 'gh');
    }

    public function testInvalidConnectionCheck(): void
    {
        $client = ClientBuilder::create()
            ->withDriver('bolt', 'bolt://localboast')
            ->withDriver('neo4j', 'neo4j://localboast')
            ->withDriver('http', 'http://localboast')
            ->build();

        self::assertFalse($client->verifyConnectivity('bolt'));
        self::assertFalse($client->verifyConnectivity('neo4j'));
        self::assertFalse($client->verifyConnectivity('http'));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testValidConnectionCheck(string $alias): void
    {
        self::assertTrue($this->getClient()->verifyConnectivity($alias));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testFetchSize(string $alias): void
    {
        $this->fetchSize($alias, 1);
        $this->fetchSize($alias, 4);
        $this->fetchSize($alias, 10);
    }

    public function fetchSize(string $connection, int $fetchSize): void
    {
        $session = $this->getClient()->getDriver($connection)->createSession(SessionConfiguration::default()->withFetchSize($fetchSize));
        $session->run('MATCH (x) DETACH DELETE x');

        $nodesAmount = $fetchSize * 4;
        // Add user nodes
        for ($i = 0; $i < $nodesAmount; ++$i) {
            $session->run('CREATE (user:User)');
        }

        // Confirm that the database contains 4000 unique user nodes
        $userCountResults = $session->run('MATCH (user:User) RETURN COUNT(DISTINCT(ID(user))) as user_count');
        $userCount = $userCountResults->getAsCypherMap(0)->getAsInt('user_count');

        $this->assertEquals($nodesAmount, $userCount);

        // Retrieve the ids of all user nodes
        $results = $session->run('MATCH (user:User) RETURN ID(user) AS id');

        // Loop through the results and add each id to an array
        $userIds = [];
        foreach ($results as $result) {
            $userIds[] = $result->get('id');
        }

        $this->assertCount($nodesAmount, $userIds);

        // Check if we have any duplicate ids by removing duplicate values
        // from the array.
        $uniqueUserIds = array_unique($userIds);

        $this->assertCount($nodesAmount, $uniqueUserIds);
    }

    public function testRedundantAcquire(): void
    {
        $connections = self::buildConnections();

        $builder = ClientBuilder::create();
        foreach ($connections as $i => $connection) {
            $uri = Uri::create($connection);
            $alias = $uri->getScheme().'_'.$i;
            $builder = $builder->withDriver($alias, $connection);
        }

        $client = $builder->withFormatter(ClientIntegrationTest::formatter())
            ->withDefaultSessionConfiguration(SessionConfiguration::default()->withDatabase('neo4j'))
            ->build();

        foreach ($connections as $i => $connection) {
            $uri = Uri::create($connection);
            $alias = $uri->getScheme().'_'.$i;
            $client->run('MATCH (x) RETURN x', [], $alias);

            $driver = $client->getDriver($alias);

            // We make sure there is no redundant acquire by testing the amount of open connections.
            // These may never exceed 1 in this simple case.
            if ($driver instanceof BoltDriver) {
                $reflection = new ReflectionClass($driver);

                $poolProp = $reflection->getProperty('pool');
                /** @var ConnectionPool $pool */
                $pool = $poolProp->getValue($driver);

                $reflection = new ReflectionClass($pool);
                $connectionProp = $reflection->getProperty('activeConnections');
                /** @var array $activeConnections */
                $activeConnections = $connectionProp->getValue($pool);

                $this->assertCount(1, $activeConnections);
            }
        }
    }
}
