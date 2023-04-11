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

use Laudis\Neo4j\Bolt\BoltDriver;
use Laudis\Neo4j\Bolt\Connection;
use Laudis\Neo4j\Bolt\ConnectionPool;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use ReflectionClass;
use Throwable;

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

    public function testCommitInvalid(): void
    {
        $tsx = $this->getSession()->beginTransaction();
        $tsx->commit();

        self::assertTrue($tsx->isFinished());
        self::assertFalse($tsx->isRolledBack());
        self::assertTrue($tsx->isCommitted());

        $exception = false;
        try {
            $tsx->commit();
        } catch (Throwable) {
            $exception = true;
        }
        self::assertTrue($exception);

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

    public function testRollbackInvalid(): void
    {
        $tsx = $this->getSession()->beginTransaction();
        $tsx->rollback();

        self::assertTrue($tsx->isFinished());
        self::assertTrue($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());

        $exception = false;
        try {
            $tsx->rollback();
        } catch (Throwable) {
            $exception = true;
        }
        self::assertTrue($exception);

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

    /**
     * @doesNotPerformAssertions
     */
    public function testTransactionRunNoConsumeResult(): void
    {
        $tsx = $this->getSession()->beginTransaction([]);
        $tsx->run('MATCH (x) RETURN x');
        $tsx->run('MATCH (x) RETURN x');
        $tsx->commit();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testTransactionRunNoConsumeButSaveResult(): void
    {
        $tsx = $this->getSession()->beginTransaction([]);
        $result = $tsx->run("MATCH (n:Node) SET n.testing = 'world' RETURN n");
        $tsx->commit();

        unset($result);
    }
}
