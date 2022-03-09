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

use Laudis\Neo4j\Bolt\BoltConnectionPool;
use Laudis\Neo4j\Bolt\BoltDriver;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\BasicFormatter;
use ReflectionClass;
use function str_starts_with;

/**
 * @psalm-import-type BasicResults from \Laudis\Neo4j\Formatter\BasicFormatter
 *
 * @extends EnvironmentAwareIntegrationTest<BasicResults>
 */
final class TransactionIntegrationTest extends EnvironmentAwareIntegrationTest
{
    protected static function formatter(): FormatterInterface
    {
        return new BasicFormatter();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testValidRun(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $tsx = $this->getClient()->beginTransaction(null, $alias);

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
        self::assertEquals(['test' => 'a'], $map->get('x'));
        self::assertEquals(['test' => 'b'], $map->get('y'));
        self::assertEquals('a', $map->get('test'));
        self::assertEquals(['c' => 'd'], $map->get('map'));
        self::assertEquals([1, 2, 3], $map->get('list'));

        self::assertFalse($tsx->isFinished());
        self::assertFalse($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testInvalidRun(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $tsx = $this->getClient()->beginTransaction(null, $alias);

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

    /**
     * @dataProvider connectionAliases
     */
    public function testValidStatement(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $response = $this->getClient()->beginTransaction(null, $alias)->runStatement(
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
        self::assertEquals(['test' => 'a'], $map->get('x'));
        self::assertEquals(['test' => 'b'], $map->get('y'));
        self::assertEquals('a', $map->get('test'));
        self::assertEquals(['c' => 'd'], $map->get('map'));
        self::assertEquals([1, 2, 3], $map->get('list'));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testInvalidStatement(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $transaction = $this->getClient()->beginTransaction(null, $alias);
        $exception = false;
        try {
            $statement = Statement::create('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b']);
            $transaction->runStatement($statement);
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

        $transaction = $this->getClient()->beginTransaction(null, $alias);
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

    /**
     * @dataProvider connectionAliases
     */
    public function testInvalidStatements(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $tsx = $this->getClient()->beginTransaction(null, $alias);

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
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);

        self::assertTrue($tsx->isFinished());
        self::assertTrue($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testCommitValidEmpty(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $tsx = $this->getClient()->beginTransaction(null, $alias);

        self::assertFalse($tsx->isFinished());
        self::assertFalse($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());

        $result = $tsx->commit();
        self::assertEquals(0, $result->count());

        self::assertTrue($tsx->isFinished());
        self::assertFalse($tsx->isRolledBack());
        self::assertTrue($tsx->isCommitted());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testCommitValidFilled(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $result = $this->getClient()->beginTransaction(null, $alias)->commit([Statement::create(<<<'CYPHER'
UNWIND [1, 2, 3] AS x
RETURN x
CYPHER
        )]);
        self::assertEquals(1, $result->count());
        self::assertEquals(3, $result->first()->count());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testCommitValidFilledWithInvalidStatement(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $tsx = $this->getClient()->beginTransaction(null, $alias);

        $exception = false;
        try {
            $tsx->commit([Statement::create('adkjbehqjk')]);
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);
        self::assertTrue($tsx->isFinished());
        self::assertTrue($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testCommitInvalid(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $tsx = $this->getClient()->beginTransaction(null, $alias);
        $tsx->commit();

        self::assertTrue($tsx->isFinished());
        self::assertFalse($tsx->isRolledBack());
        self::assertTrue($tsx->isCommitted());

        $exception = false;
        try {
            $tsx->commit();
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);

        self::assertTrue($tsx->isFinished());
        self::assertFalse($tsx->isRolledBack());
        self::assertTrue($tsx->isCommitted());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testRollbackValid(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $tsx = $this->getClient()->beginTransaction(null, $alias);
        $tsx->rollback();

        self::assertTrue($tsx->isFinished());
        self::assertTrue($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testRollbackInvalid(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $tsx = $this->getClient()->beginTransaction(null, $alias);
        $tsx->rollback();

        self::assertTrue($tsx->isFinished());
        self::assertTrue($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());

        $exception = false;
        try {
            $tsx->rollback();
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);

        self::assertTrue($tsx->isFinished());
        self::assertTrue($tsx->isRolledBack());
        self::assertFalse($tsx->isCommitted());
    }

    /**
     * @dataProvider connectionAliases
     * @noinspection PhpUnusedLocalVariableInspection
     * @psalm-suppress UnusedVariable
     */
    public function testCorrectConnectionReuse(string $alias): void
    {
        $driver = $this->getClient()->getDriver($alias);
        if (!$driver instanceof BoltDriver) {
            self::markTestSkipped('Can only white box test bolt driver');
        }

        $poolReflection = new ReflectionClass(BoltConnectionPool::class);
        $poolReflection->setStaticPropertyValue('connectionCache', []);

        $this->getClient()->run('MATCH (x) RETURN x', [], $alias);
        $this->getClient()->run('MATCH (x) RETURN x', [], $alias);
        $this->getClient()->run('MATCH (x) RETURN x', [], $alias);
        $this->getClient()->run('MATCH (x) RETURN x', [], $alias);
        $a = $this->getClient()->beginTransaction([], $alias);
        $b = $this->getClient()->beginTransaction([], $alias);
        $this->getClient()->run('MATCH (x) RETURN x', [], $alias);

        $poolReflection = new ReflectionClass(BoltConnectionPool::class);
        /** @var array $cache */
        $cache = $poolReflection->getStaticPropertyValue('connectionCache');

        $key = array_key_first($cache);
        self::assertIsString($key);
        self::assertArrayHasKey($key, $cache);
        /** @psalm-suppress MixedArgument */
        self::assertCount(3, $cache[$key]);
    }

    /**
     * @dataProvider connectionAliases
     *
     * @doesNotPerformAssertions
     */
    public function testTransactionRunNoConsumeResult(string $alias): void
    {
        $tsx = $this->getClient()->beginTransaction([], $alias);
        $tsx->run('MATCH (x) RETURN x');
        $tsx->run('MATCH (x) RETURN x');
        $tsx->commit();
    }
}
