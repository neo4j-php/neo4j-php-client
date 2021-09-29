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

use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\BasicFormatter;

/**
 * @psalm-import-type BasicResults from \Laudis\Neo4j\Formatter\BasicFormatter
 *
 * @extends EnvironmentAwareIntegrationTest<\Laudis\Neo4j\Types\CypherList<\Laudis\Neo4j\Types\CypherMap<scalar|array|null>>>
 */
final class TransactionIntegrationTest extends EnvironmentAwareIntegrationTest
{
    protected function formatter(): FormatterInterface
    {
        return new BasicFormatter();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testMultipleTransactions(string $alias): void
    {
        $this->client->run('MATCH (x) DETACH DELETE (x)', [], $alias);

        for ($i = 0; $i < 2; ++$i) {
            $tsxs = [];
            for ($j = 0; $j < 100; ++$j) {
                $tsxs[] = $this->client->beginTransaction(null, $alias);
            }

            foreach ($tsxs as $tsx) {
                $tsx->run('CREATE (:X {y: "z"})');
            }

            self::assertEquals(0 + $i * 100, $this->client->run('MATCH (x) RETURN count(x) AS x', [], $alias)->first()->get('x'));

            foreach ($tsxs as $j => $tsx) {
                $tsx->commit();

                self::assertEquals($j + 1 + $i * 100, $this->client->run('MATCH (x) RETURN count(x) AS x', [], $alias)->first()->get('x'));
            }

            self::assertEquals(($i + 1) * 100, $this->client->run('MATCH (x) RETURN count(x) AS x', [], $alias)->first()->get('x'));
        }
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testValidRun(string $alias): void
    {
        $response = $this->client->beginTransaction(null, $alias)->run(<<<'CYPHER'
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
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testInvalidRun(string $alias): void
    {
        $transaction = $this->client->beginTransaction(null, $alias);
        $exception = false;
        try {
            $transaction->run('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b']);
        } catch (Neo4jException $e) {
            $exception = true;
            self::assertEquals('Neo.ClientError.Statement.SyntaxError', $e->getNeo4jCode());
        }
        self::assertTrue($exception);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testValidStatement(string $alias): void
    {
        $response = $this->client->beginTransaction(null, $alias)->runStatement(
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
        $transaction = $this->client->beginTransaction(null, $alias);
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
        $transaction = $this->client->beginTransaction(null, $alias);
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
        $transaction = $this->client->beginTransaction(null, $alias);
        $exception = false;
        try {
            $params = ['test' => 'a', 'otherTest' => 'b'];
            $transaction->runStatements([
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
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testCommitValidEmpty(string $alias): void
    {
        $result = $this->client->beginTransaction(null, $alias)->commit();
        self::assertEquals(0, $result->count());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testCommitValidFilled(string $alias): void
    {
        $result = $this->client->beginTransaction(null, $alias)->commit([Statement::create(<<<'CYPHER'
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
        $transaction = $this->client->beginTransaction(null, $alias);
        $exception = false;
        try {
            $transaction->commit([Statement::create('adkjbehqjk')]);
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testCommitInvalid(string $alias): void
    {
        $transaction = $this->client->beginTransaction(null, $alias);
        $transaction->commit();
        $exception = false;
        try {
            $transaction->commit();
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testRollbackValid(string $alias): void
    {
        $transaction = $this->client->beginTransaction(null, $alias);
        $transaction->rollback();
        self::assertTrue(true);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testRollbackInvalid(string $alias): void
    {
        $transaction = $this->client->beginTransaction(null, $alias);
        $transaction->rollback();
        $exception = false;
        try {
            $transaction->rollback();
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);
    }
}
