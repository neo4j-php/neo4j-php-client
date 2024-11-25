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

use InvalidArgumentException;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Basic\Driver;
use Laudis\Neo4j\Bolt\BoltDriver;
use Laudis\Neo4j\Bolt\ConnectionPool;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Common\DriverSetupManager;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\DriverSetup;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Tests\EnvironmentAwareIntegrationTest;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use ReflectionClass;
use RuntimeException;

final class ClientIntegrationTest extends EnvironmentAwareIntegrationTest
{
    public function testDriverAuthFailureVerifyConnectivity(): void
    {
        $connection = $_ENV['CONNECTION'] ?? false;
        if (str_starts_with((string) $connection, 'http')) {
            $this->markTestSkipped('HTTP does not support auth failure connectivity passing');
        }

        if (!is_string($connection)) {
            $connection = 'bolt://localhost';
        }

        $uri = Uri::create($connection);
        $uri = $uri->withUserInfo('neo4j', 'absolutelyonehundredpercentawrongpassword');

        /** @noinspection PhpUnhandledExceptionInspection */
        $conf = DriverConfiguration::default()->withLogger(LogLevel::DEBUG, $this->createMock(LoggerInterface::class));
        $logger = $conf->getLogger();
        if ($logger === null) {
            throw new RuntimeException('Logger not set');
        }

        $driver = Driver::create($uri, $conf);

        $this->expectException(Neo4jException::class);
        $this->expectExceptionMessage(
            'Neo4j errors detected. First one with code "Neo.ClientError.Security.Unauthorized" and message "The client is unauthorized due to authentication failure."'
        );
        $driver->verifyConnectivity();
    }

    public function testClientAuthFailureVerifyConnectivity(): void
    {
        $connection = $_ENV['CONNECTION'] ?? false;
        if (str_starts_with((string) $connection, 'http')) {
            $this->markTestSkipped('HTTP does not support auth failure connectivity passing');
        }

        if (!is_string($connection)) {
            $connection = 'bolt://localhost';
        }

        $uri = Uri::create($connection);
        $uri = $uri->withUserInfo('neo4j', 'absolutelyonehundredpercentawrongpassword');

        /** @noinspection PhpUnhandledExceptionInspection */
        $conf = DriverConfiguration::default()->withLogger(LogLevel::DEBUG, $this->createMock(LoggerInterface::class));
        $logger = $conf->getLogger();
        if ($logger === null) {
            throw new RuntimeException('Logger not set');
        }

        $client = (new ClientBuilder(
            SessionConfiguration::create(),
            TransactionConfiguration::create(),
            (new DriverSetupManager(
                SummarizedResultFormatter::create(),
                $conf,
            ))->withSetup(
                new DriverSetup($uri, Authenticate::fromUrl($uri, $logger))
            )
        ))->build();

        $driver = $client->getDriver(null);

        $this->expectException(Neo4jException::class);
        $this->expectExceptionMessage(
            'Neo4j errors detected. First one with code "Neo.ClientError.Security.Unauthorized" and message "The client is unauthorized due to authentication failure."'
        );
        $driver->verifyConnectivity();
    }

    public function testDifferentAuth(): void
    {
        $auth = Authenticate::fromUrl($this->getUri());
        $uri = $this->getUri()->withUserInfo('');

        $driver = Driver::create($uri, null, $auth);
        self::assertTrue($driver->verifyConnectivity());

        self::assertEquals(1, $driver->createSession()->run('RETURN 1 AS one')->first()->get('one'));
    }

    public function testAvailabilityFullImplementation(): void
    {
        $transaction = $this->getSession()->beginTransaction();
        $results = $transaction
            ->run('UNWIND [1] AS x RETURN x')
            ->first()
            ->get('x');
        $transaction->rollback();
        self::assertEquals(1, $results);
    }

    public function testTransactionFunction(): void
    {
        $result = $this->getSession()->transaction(
            static fn (TransactionInterface $tsx) => $tsx->run('UNWIND [1] AS x RETURN x')->first()->getAsInt('x')
        );

        self::assertEquals(1, $result);

        $result = $this->getSession()->readTransaction(
            static fn (TransactionInterface $tsx) => $tsx->run('UNWIND [1] AS x RETURN x')->first()->getAsInt('x')
        );

        self::assertEquals(1, $result);

        $result = $this->getSession()->writeTransaction(
            static fn (TransactionInterface $tsx) => $tsx->run('UNWIND [1] AS x RETURN x')->first()->getAsInt('x')
        );

        self::assertEquals(1, $result);
    }

    public function testValidRun(): void
    {
        $response = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->run(
            <<<'CYPHER'
MERGE (x:TestNode {test: $test})
WITH x
MERGE (y:OtherTestNode {test: $otherTest})
WITH x, y, {c: 'd'} AS map, [1, 2, 3] AS list
RETURN x, y, x.test AS test, map, list
CYPHER,
            ['test' => 'a', 'otherTest' => 'b']
        ));

        self::assertEquals(1, $response->count());
        $map = $response->first();
        self::assertEquals(5, $map->count());
        self::assertEquals(['test' => 'a'], $map->getAsNode('x')->getProperties()->toArray());
        self::assertEquals(['test' => 'b'], $map->getAsNode('y')->getProperties()->toArray());
        self::assertEquals('a', $map->get('test'));
        self::assertEquals(['c' => 'd'], $map->getAsMap('map')->toArray());
        self::assertEquals([1, 2, 3], $map->getAsArrayList('list')->toArray());
    }

    public function testInvalidRun(): void
    {
        $this->expectException(Neo4jException::class);
        $this->getSession()->transaction(
            static fn (TransactionInterface $tsx) => $tsx->run(
                'MERGE (x:Tes0342hdm21.())',
                ['test' => 'a', 'otherTest' => 'b']
            )
        );
    }

    public function testInvalidRunRetry(): void
    {
        $exception = false;
        try {
            $this->getSession()->run('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b']);
        } catch (Neo4jException) {
            $exception = true;
        }
        self::assertTrue($exception);

        $response = $this->getSession()->run('RETURN 1 AS one');
        $this->assertEquals(1, $response->first()->get('one'));
    }

    public function testValidStatement(): void
    {
        $response = $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->runStatement(
            Statement::create(
                <<<'CYPHER'
MERGE (x:TestNode {test: $test})
WITH x
MERGE (y:OtherTestNode {test: $otherTest})
WITH x, y, {c: 'd'} AS map, [1, 2, 3] AS list
RETURN x, y, x.test AS test, map, list
CYPHER,
                ['test' => 'a', 'otherTest' => 'b']
            )
        ));

        self::assertEquals(1, $response->count());
        $map = $response->first();
        self::assertEquals(5, $map->count());
        self::assertEquals(['test' => 'a'], $map->getAsNode('x')->getProperties()->toArray());
        self::assertEquals(['test' => 'b'], $map->getAsNode('y')->getProperties()->toArray());
        self::assertEquals('a', $map->get('test'));
        self::assertEquals(['c' => 'd'], $map->getAsMap('map')->toArray());
        self::assertEquals([1, 2, 3], $map->getAsArrayList('list')->toArray());
    }

    public function testInvalidStatement(): void
    {
        $this->expectException(Neo4jException::class);
        $statement = Statement::create('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b']);
        $this->getSession()->transaction(static fn (TransactionInterface $tsx) => $tsx->runStatement($statement));
    }

    public function testStatements(): void
    {
        $params = ['test' => 'a', 'otherTest' => 'b'];
        $response = $this->getSession()->runStatements([
            Statement::create('MERGE (x:TestNode {test: $test})', $params),
            Statement::create('MERGE (x:OtherTestNode {test: $otherTest})', $params),
            Statement::create('RETURN 1 AS x'),
        ]);

        self::assertEquals(3, $response->count());
        self::assertEquals(0, $response->get(0)->count());
        self::assertEquals(0, $response->get(1)->count());
        self::assertEquals(1, $response->get(2)->count());
        self::assertEquals(1, $response->get(2)->first()->get('x'));
    }

    public function testInvalidStatements(): void
    {
        $this->expectException(Neo4jException::class);
        $params = ['test' => 'a', 'otherTest' => 'b'];
        $this->getSession()->runStatements([
            Statement::create('MERGE (x:TestNode {test: $test})', $params),
            Statement::create('MERGE (x:OtherTestNode {test: $otherTest})', $params),
            Statement::create('1 AS x;erns', []),
        ]);
    }

    public function testMultipleTransactions(): void
    {
        $x = $this->getSession()->beginTransaction();
        $y = $this->getSession()->beginTransaction();
        self::assertNotSame($x, $y);
        $x->rollback();
        $y->rollback();
    }

    public function testInvalidConnection(): void
    {
        $client = ClientBuilder::create()->build();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot find a driver setup with alias: "gh"');

        $client->transaction(static fn (TransactionInterface $tsx) => $tsx->run('RETURN 1 AS x'), 'gh');
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

    public function testValidConnectionCheck(): void
    {
        self::assertTrue($this->getDriver()->verifyConnectivity());
    }

    public function testFetchSize(): void
    {
        $this->fetchSize(1);
        $this->fetchSize(4);
        $this->fetchSize(10);
    }

    public function fetchSize(int $fetchSize): void
    {
        $session = $this->getDriver()->createSession(SessionConfiguration::default()->withFetchSize($fetchSize));

        $nodesAmount = $fetchSize * 4;

        // Retrieve the ids of all user nodes
        $results = $session->run('UNWIND range(1, $x) AS id RETURN id', ['x' => $nodesAmount]);

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
        self::setUpBeforeClass(); // forces the destructors to come in and rebuild the connection pool.

        $this->getSession()->run('MATCH (x) RETURN x');
        $driver = $this->getDriver('bolt');
        $reflection = new ReflectionClass($driver);
        $property = $reflection->getProperty('driver');
        $property->setAccessible(true);
        /** @var DriverInterface $driver */
        $driver = $property->getValue($driver);

        // We make sure there is no redundant acquire by testing the amount of open connections.
        // These may never exceed 1 in this simple case.
        if ($driver instanceof BoltDriver) {
            $reflection = new ReflectionClass($driver);

            $poolProp = $reflection->getProperty('pool');
            $poolProp->setAccessible(true);
            /** @var ConnectionPool $pool */
            $pool = $poolProp->getValue($driver);

            $reflection = new ReflectionClass($pool);
            $connectionProp = $reflection->getProperty('activeConnections');
            $connectionProp->setAccessible(true);
            /** @var array $activeConnections */
            $activeConnections = $connectionProp->getValue($pool);

            $this->assertCount(1, $activeConnections);
        }
    }
}
