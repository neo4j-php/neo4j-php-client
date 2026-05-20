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

use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Exception\TransactionException;
use Laudis\Neo4j\Tests\EnvironmentAwareIntegrationTest;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
final class TransactionIntegrationTest extends EnvironmentAwareIntegrationTest
{
    public function testValidRun(): void
    {
        $tsx = $this->getSession()->beginTransaction();

        self::assertFalse($tsx->isFinished());
        self::assertFalse($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());

        $response = $tsx->run(<<<'CYPHER'
MERGE (x:TestNode {test: $test})
WITH x
MERGE (y:OtherTestNode {test: $otherTest})
WITH x, y, {c: 'd'} AS map, [1, 2, 3] AS list
RETURN x, y, x.test AS test, map, list
CYPHER, ['test' => 'a', 'otherTest' => 'b']);

        self::assertEquals(1, $response->count());
        $map = $response->first();
        self::assertEquals(5, $map->count());
        self::assertEquals(['test' => 'a'], $map->getAsNode('x')->getProperties()->toArray());
        self::assertEquals(['test' => 'b'], $map->getAsNode('y')->getProperties()->toArray());
        self::assertEquals('a', $map->get('test'));
        self::assertEquals(['c' => 'd'], $map->getAsMap('map')->toArray());
        self::assertEquals([1, 2, 3], $map->getAsArrayList('list')->toArray());

        self::assertFalse($tsx->isFinished());
        self::assertFalse($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());
    }

    public function testInvalidRun(): void
    {
        $tsx = $this->getSession()->beginTransaction();

        self::assertFalse($tsx->isFinished());
        self::assertFalse($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());

        $exception = false;
        try {
            $tsx->run('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b']);
        } catch (Neo4jException $e) {
            $exception = true;
            self::assertEquals('Neo.ClientError.Statement.SyntaxError', $e->getNeo4jCode());
        }
        self::assertTrue($exception);
        self::assertTrue($tsx->isFinished());
        self::assertTrue($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());
    }

    public function testValidStatement(): void
    {
        $response = $this->getSession()->beginTransaction()->runStatement(
            Statement::create(<<<'CYPHER'
MERGE (x:TestNode {test: $test})
WITH x
MERGE (y:OtherTestNode {test: $otherTest})
WITH x, y, {c: 'd'} AS map, [1, 2, 3] AS list
RETURN x, y, x.test AS test, map, list
CYPHER, ['test' => 'a', 'otherTest' => 'b'])
        );

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
        $transaction = $this->getSession()->beginTransaction();
        $exception = false;
        try {
            $statement = Statement::create('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b']);
            $transaction->runStatement($statement);
        } catch (Neo4jException) {
            $exception = true;
        }
        self::assertTrue($exception);
    }

    public function testStatements(): void
    {
        $transaction = $this->getSession()->beginTransaction();
        $params = ['test' => 'a', 'otherTest' => 'b'];
        $response = $transaction->runStatements([
            Statement::create(<<<'CYPHER'
MERGE (x:TestNode {test: $test})
CYPHER,
                $params
            ),
            Statement::create(<<<'CYPHER'
MERGE (x:OtherTestNode {test: $otherTest})
CYPHER,
                $params
            ),
            Statement::create(<<<'CYPHER'
RETURN 1 AS x
CYPHER,
                []
            ),
        ]);

        self::assertEquals(3, $response->count());
        self::assertEquals(0, $response->get(0)->count());
        self::assertEquals(0, $response->get(1)->count());
        self::assertEquals(1, $response->get(2)->count());
        self::assertEquals(1, $response->get(2)->first()->get('x'));
    }

    public function testInvalidStatements(): void
    {
        $tsx = $this->getSession()->beginTransaction();

        self::assertFalse($tsx->isFinished());
        self::assertFalse($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());

        $exception = false;
        try {
            $params = ['test' => 'a', 'otherTest' => 'b'];
            $tsx->runStatements([
                Statement::create(<<<'CYPHER'
MERGE (x:TestNode {test: $test})
CYPHER,
                    $params
                ),
                Statement::create(<<<'CYPHER'
MERGE (x:OtherTestNode {test: $otherTest})
CYPHER,
                    $params
                ),
                Statement::create('1 AS x;erns', []),
            ]);
        } catch (Neo4jException) {
            $exception = true;
        }
        self::assertTrue($exception);

        self::assertTrue($tsx->isFinished());
        self::assertTrue($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());
    }

    public function testCommitValidEmpty(): void
    {
        $tsx = $this->getSession()->beginTransaction();

        self::assertFalse($tsx->isFinished());
        self::assertFalse($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());

        $result = $tsx->commit();
        self::assertEquals(0, $result->count());

        self::assertTrue($tsx->isFinished());
        self::assertFalse($tsx->isRolledBack());
        self::assertTrue($tsx->isCommitted());
        self::assertFalse($this->getSession()->getLastBookmark()->isEmpty());
    }

    public function testCommitValidFilled(): void
    {
        $result = $this->getSession()->beginTransaction()->commit([Statement::create(<<<'CYPHER'
UNWIND [1, 2, 3] AS x
RETURN x
CYPHER
        )]);
        self::assertEquals(1, $result->count());
        self::assertEquals(3, $result->first()->count());
    }

    public function testCommitValidFilledWithInvalidStatement(): void
    {
        $tsx = $this->getSession()->beginTransaction();

        $exception = false;
        try {
            $tsx->commit([Statement::create('adkjbehqjk')]);
        } catch (Neo4jException) {
            $exception = true;
        }
        self::assertTrue($exception);
        self::assertTrue($tsx->isFinished());
        self::assertTrue($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());
    }

    // TODO commit on READY state cause stuck neo4j connection on older version and disconnect at newer
    public function testCommitInvalid(): void
    {
        $tsx = $this->getSession()->beginTransaction();
        $tsx->commit();

        self::assertTrue($tsx->isFinished());
        self::assertFalse($tsx->isRolledBack());
        self::assertTrue($tsx->isCommitted());

        $exception = null;
        try {
            $tsx->commit();
        } catch (TransactionException|Neo4jException $e) {
            $exception = $e;
        }

        self::assertTrue($exception instanceof TransactionException);

        self::assertTrue($tsx->isFinished());
        self::assertFalse($tsx->isRolledBack());
        self::assertTrue($tsx->isCommitted());
    }

    public function testRollbackValid(): void
    {
        $tsx = $this->getSession()->beginTransaction();
        $tsx->rollback();

        self::assertTrue($tsx->isFinished());
        self::assertTrue($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());
    }

    // TODO rollback on READY state cause stuck neo4j connection on older version and disconnect at newer
    public function testRollbackInvalid(): void
    {
        $tsx = $this->getSession()->beginTransaction();
        $tsx->rollback();

        self::assertTrue($tsx->isFinished());
        self::assertTrue($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());

        $exception = null;
        try {
            $tsx->rollback();
        } catch (TransactionException|Neo4jException $e) {
            $exception = $e;
        }

        self::assertTrue($exception instanceof TransactionException);

        self::assertTrue($tsx->isFinished());
        self::assertTrue($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());
    }

    //    /**
    //     * TODO - rework this test
    //     * @dataProvider connectionAliases
    //     * @noinspection PhpUnusedLocalVariableInspection
    //     * @psalm-suppress UnusedVariable
    //     */
    //    public function testCorrectConnectionReuse(): void
    //    {
    //        $driver = $this->getSession()->getDriver($alias);
    //        if (!$driver instanceof BoltDriver) {
    //            self::markTestSkipped('Can only white box test bolt driver');
    //        }
    //
    //        $poolReflection = new ReflectionClass(Connection::class);
    //        $poolReflection->setStaticPropertyValue('connectionCache', []);
    //
    //        $this->getSession()->run('MATCH (x) RETURN x', []);
    //        $this->getSession()->run('MATCH (x) RETURN x', []);
    //        $this->getSession()->run('MATCH (x) RETURN x', []);
    //        $this->getSession()->run('MATCH (x) RETURN x', []);
    //        $a = $this->getSession()->beginTransaction([]);
    //        $b = $this->getSession()->beginTransaction([]);
    //        $this->getSession()->run('MATCH (x) RETURN x', []);
    //
    //        $poolReflection = new ReflectionClass(ConnectionPool::class);
    //        /** @var array $cache */
    //        $cache = $poolReflection->getStaticPropertyValue('connectionCache');
    //
    //        $key = array_key_first($cache);
    //        self::assertIsString($key);
    //        self::assertArrayHasKey($key, $cache);
    //        /** @psalm-suppress MixedArgument */
    //        self::assertCount(3, $cache[$key]);
    //    }

    #[DoesNotPerformAssertions]
    public function testTransactionRunNoConsumeResult(): void
    {
        $tsx = $this->getSession()->beginTransaction([]);
        $tsx->run('MATCH (x) RETURN x');
        $tsx->run('MATCH (x) RETURN x');
        $tsx->commit();
    }

    public function testRunAfterCommit(): void
    {
        $tsx = $this->getSession()->beginTransaction([]);
        $tsx->run('MATCH (x) RETURN x');
        $tsx->run('MATCH (x) RETURN x');
        $tsx->commit();

        $this->expectException(TransactionException::class);
        $tsx->run('MATCH (x) RETURN x');
    }

    #[DoesNotPerformAssertions]
    public function testTransactionRunNoConsumeButSaveResult(): void
    {
        $tsx = $this->getSession()->beginTransaction([]);
        $result = $tsx->run("MATCH (n:Node) SET n.testing = 'world' RETURN n");
        $tsx->commit();

        unset($result);
    }

    /**
     * Bolt spec: only autocommit RUN carries db/timeout/bookmarks/metadata in `extra`.
     * RUN inside an explicit transaction must send an empty `extra` map. Strict servers
     * (e.g. Neo4j Aura) reject the violation and surface it as DatabaseNotFound, which
     * was the root cause of writeTransaction() failing against Aura while autocommit
     * session.run() worked. See https://github.com/neo4j-php/neo4j-php-client/issues/298
     *
     * This test asserts the wire-level contract by capturing BEGIN/RUN debug log entries.
     */
    public function testManagedTransactionRunSendsEmptyExtras(): void
    {
        if (str_contains($this->getUri()->getScheme(), 'http')) {
            self::markTestSkipped('This test is not applicable for the HTTP driver');
        }

        $this->driver->closeConnections();

        $logger = $this->mockLogger;

        $debugLogs = [];
        $logger
            ->method('debug')
            ->willReturnCallback(static function (string $msg, array $ctx) use (&$debugLogs): void {
                $debugLogs[] = [$msg, $ctx];
            });

        $session = $this->driver->createSession();

        $session->run('RETURN 1 AS x')->preload();

        $session->writeTransaction(
            static fn (TransactionInterface $tsx) => $tsx->run('RETURN 1 AS x')
        );

        $beginEntries = array_values(array_filter($debugLogs, static fn (array $e): bool => $e[0] === 'BEGIN'));
        $runEntries = array_values(array_filter($debugLogs, static fn (array $e): bool => $e[0] === 'RUN'));

        self::assertNotEmpty($beginEntries, 'expected at least one BEGIN debug log entry');
        self::assertNotEmpty($runEntries, 'expected at least one RUN debug log entry');

        self::assertArrayHasKey('extra', $runEntries[1][1]);
        self::assertSame(
            [],
            $runEntries[1][1]['extra'],
            'RUN inside an explicit transaction must have an empty `extra` map per the Bolt spec'
        );
    }

    /**
     * Regression for the "Undefined array key rt" PHP fatal that occurred whenever the
     * Bolt ROUTE message returned FAILURE (e.g. when the requested database does not
     * exist). All other Bolt messages call assertNoFailure() on their response; ROUTE
     * was the lone exception, so a server FAILURE was silently ignored and the missing
     * `rt` field crashed the driver. After the fix the failure surfaces cleanly as a
     * Neo4jException carrying the server's real code.
     */
    public function testRoutedSessionWithMissingDatabaseSurfacesNeo4jException(): void
    {
        if (!str_starts_with($this->getUri()->getScheme(), 'neo4j')) {
            self::markTestSkipped('This test requires the neo4j:// (routing) scheme');
        }

        $database = 'definitely-does-not-exist-'.bin2hex(random_bytes(4));
        $session = $this->driver->createSession(
            SessionConfiguration::default()->withDatabase($database)
        );

        try {
            $session->run('RETURN 1');
            self::fail('expected Neo4jException for non-existent database');
        } catch (Neo4jException $e) {
            self::assertSame('Neo.ClientError.Database.DatabaseNotFound', $e->getNeo4jCode());
        }
    }
}
