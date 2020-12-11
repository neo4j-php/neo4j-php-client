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

namespace Laudis\Neo4j\Tests\Base;

use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use PHPUnit\Framework\TestCase;

abstract class ClientTest extends TestCase
{
    private ClientInterface $client;

    abstract public function createClient(): ClientInterface;

    /**
     * @return iterable<int, string>
     */
    abstract public function connectionAliases(): iterable;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createClient();
    }

    public function testValidRun(): void
    {
        foreach ($this->connectionAliases() as $alias) {
            $response = $this->client->run(<<<'CYPHER'
MERGE (x:TestNode {test: $test})
WITH x
MERGE (y:OtherTestNode {test: $otherTest})
WITH x, y, {c: 'd'} AS map, [1, 2, 3] AS list
RETURN x, y, x.test AS test, map, list
CYPHER, ['test' => 'a', 'otherTest' => 'b'], $alias);

            self::assertEquals(1, $response->count());
            $map = $response->first();
            self::assertEquals(5, $map->count());
            self::assertEquals(['test' => 'a'], $map->get('x'));
            self::assertEquals(['test' => 'b'], $map->get('y'));
            self::assertEquals('a', $map->get('test'));
            self::assertEquals(['c' => 'd'], $map->get('map'));
            self::assertEquals([1, 2, 3], $map->get('list'));
        }
    }

    public function testInvalidRun(): void
    {
        foreach ($this->connectionAliases() as $alias) {
            $exception = false;
            try {
                $this->client->run('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b'], $alias);
            } catch (Neo4jException $e) {
                $exception = true;
            }
            self::assertTrue($exception);
        }
    }

    public function testValidStatement(): void
    {
        foreach ($this->connectionAliases() as $alias) {
            $response = $this->client->runStatement(
                Statement::create(<<<'CYPHER'
MERGE (x:TestNode {test: $test})
WITH x
MERGE (y:OtherTestNode {test: $otherTest})
WITH x, y, {c: 'd'} AS map, [1, 2, 3] AS list
RETURN x, y, x.test AS test, map, list
CYPHER, ['test' => 'a', 'otherTest' => 'b']),
                $alias
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
    }

    public function testInvalidStatement(): void
    {
        foreach ($this->connectionAliases() as $alias) {
            $exception = false;
            try {
                $statement = Statement::create('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b']);
                $this->client->runStatement($statement, $alias);
            } catch (Neo4jException $e) {
                $exception = true;
            }
            self::assertTrue($exception);
        }
    }

    public function testStatements(): void
    {
        foreach ($this->connectionAliases() as $alias) {
            $params = ['test' => 'a', 'otherTest' => 'b'];
            $response = $this->client->runStatements([
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
            ],
                $alias
            );

            self::assertEquals(3, $response->count());
            self::assertEquals(0, $response->get(0)->count());
            self::assertEquals(0, $response->get(1)->count());
            self::assertEquals(1, $response->get(2)->count());
            self::assertEquals(1, $response->get(2)->first()->get('x'));
        }
    }

    public function testInvalidStatements(): void
    {
        foreach ($this->connectionAliases() as $alias) {
            $exception = false;
            try {
                $params = ['test' => 'a', 'otherTest' => 'b'];
                $this->client->runStatements([
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
                ], $alias);
            } catch (Neo4jException $e) {
                $exception = true;
            }
            self::assertTrue($exception);
        }
    }

    public function testMultipleTransactions(): void
    {
        foreach ($this->connectionAliases() as $alias) {
            $x = $this->client->openTransaction(null, $alias);
            $y = $this->client->openTransaction(null, $alias);
            self::assertNotSame($x, $y);
        }
    }

    public function testInvalidConnection(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The provided alias: "ghqkneq;tr" was not found in the connection pool');

        $this->client->run('RETURN 1 AS x', [], 'ghqkneq;tr');
    }
}
