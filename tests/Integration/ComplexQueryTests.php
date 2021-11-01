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

use Bolt\error\ConnectException;
use Generator;
use function getenv;
use InvalidArgumentException;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\BasicFormatter;
use Laudis\Neo4j\ParameterHelper;
use function str_starts_with;

/**
 * @psalm-import-type BasicResults from \Laudis\Neo4j\Formatter\BasicFormatter
 *
 * @extends EnvironmentAwareIntegrationTest<BasicResults>
 */
final class ComplexQueryTests extends EnvironmentAwareIntegrationTest
{
    protected function formatter(): FormatterInterface
    {
        /** @psalm-suppress InvalidReturnStatement */
        return new BasicFormatter();
    }

    protected function createClient(): ClientInterface
    {
        $connections = $this->getConnections();

        $builder = ClientBuilder::create();
        foreach ($connections as $i => $connection) {
            $uri = Uri::create($connection);
            $builder = $builder->withDriver($uri->getScheme().'_'.$i, $connection, null, 1000000);
        }

        /** @psalm-suppress InvalidReturnStatement */
        return $builder->withFormatter($this->formatter())->build();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testListParameterHelper(string $alias): void
    {
        $result = $this->client->run(<<<'CYPHER'
MATCH (x) WHERE x.slug IN $listOrMap RETURN x
CYPHER, ['listOrMap' => ParameterHelper::asList([])], $alias);
        self::assertEquals(0, $result->count());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testValidListParameterHelper(string $alias): void
    {
        $result = $this->client->run(<<<'CYPHER'
RETURN $listOrMap AS x
CYPHER, ['listOrMap' => ParameterHelper::asList([1, 2, 3])], $alias);
        self::assertEquals(1, $result->count());
        self::assertEquals([1, 2, 3], $result->first()->get('x'));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testMergeTransactionFunction(string $alias): void
    {
        $this->expectException(Neo4jException::class);
        $this->client->writeTransaction(static function (TransactionInterface $tsx) {
            $result = $tsx->run('MERGE (x {y: "z"}:X) return x');
            /** @psalm-suppress all */
            return $result->first()->get('x')['y'];
        }, $alias);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testValidMapParameterHelper(string $alias): void
    {
        $result = $this->client->run(<<<'CYPHER'
RETURN $listOrMap AS x
CYPHER, ['listOrMap' => ParameterHelper::asMap(['a' => 'b', 'c' => 'd'])], $alias);
        self::assertEquals(1, $result->count());
        self::assertEquals(['a' => 'b', 'c' => 'd'], $result->first()->get('x'));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testArrayParameterHelper(string $alias): void
    {
        $this->client->run(<<<'CYPHER'
MERGE (x:Node {slug: 'a'})
WITH x
MATCH (x) WHERE x.slug IN $listOrMap RETURN x
CYPHER, ['listOrMap' => []], $alias);
        self::assertTrue(true);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testInvalidParameter(string $alias): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->client->run(<<<'CYPHER'
MERGE (x:Node {slug: 'a'})
WITH x
MATCH (x) WHERE x.slug IN $listOrMap RETURN x
CYPHER, ['listOrMap' => self::generate()], $alias);
    }

    private static function generate(): Generator
    {
        foreach (range(1, 3) as $x) {
            yield true => $x;
        }
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testInvalidParameters(string $alias): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @var iterable<string, iterable<mixed, mixed>|scalar|null> $generator */
        $generator = self::generate();
        $this->client->run(<<<'CYPHER'
MERGE (x:Node {slug: 'a'})
WITH x
MATCH (x) WHERE x.slug IN $listOrMap RETURN x
CYPHER, $generator, $alias);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testCreationAndResult(string $alias): void
    {
        $result = $this->client->run(<<<'CYPHER'
MERGE (x:Node {x:$x})
RETURN x
CYPHER
            , ['x' => 'x'], $alias)->first();

        self::assertEquals(['x' => 'x'], $result->get('x'));
    }

    /**
     * @dataProvider connectionAliases
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
        $result = $results->first();
        self::assertEquals(3, $result->count());
        self::assertEquals(['x' => 'x'], $result->get('x'));
        self::assertEquals([['attribute' => 'xy'], ['attribute' => 'yz']], $result->get('y'));
        self::assertEquals(['z' => 'z'], $result->get('z'));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testNullListAndMap(string $alias): void
    {
        $results = $this->client->run(<<<'CYPHER'
RETURN null AS x, [1, 2, 3] AS y, {x: 'x', y: 'y', z: 'z'} AS z
CYPHER
            , ['x' => 'x', 'xy' => 'xy', 'y' => 'y', 'yz' => 'yz', 'z' => 'z'], $alias);

        self::assertEquals(1, $results->count());
        $result = $results->first();
        self::assertEquals(3, $result->count());
        self::assertNull($result->get('x'));
        self::assertEquals([1, 2, 3], $result->get('y'));
        self::assertEquals(['x' => 'x', 'y' => 'y', 'z' => 'z'], $result->get('z'));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testListAndMapInput(string $alias): void
    {
        $results = $this->client->run(<<<'CYPHER'
MERGE (x:Node {x: $x.x})
WITH x
MERGE (y:Node {list: $y})
RETURN x, y
LIMIT 1
CYPHER
            , ['x' => ['x' => 'x'], 'y' => [1, 2, 3]], $alias);

        self::assertEquals(1, $results->count());
        $result = $results->first();
        self::assertEquals(2, $result->count());
        self::assertEquals(['x' => 'x'], $result->get('x'));
        self::assertEquals(['list' => [1, 2, 3]], $result->get('y'));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testPathReturnType(string $alias): void
    {
        $this->client->run(<<<'CYPHER'
MERGE (:Node {x: 'x'}) - [:Rel] -> (x:Node {x: 'y'})
WITH x
MERGE (x) - [:Rel] -> (:Node {x: 'z'})
CYPHER
            , [], $alias);

        $results = $this->client->run(<<<'CYPHER'
MATCH (a:Node {x: 'x'}), (b:Node {x: 'z'}), p = shortestPath((a)-[*]-(b))
RETURN p
CYPHER
            , [], $alias);

        self::assertEquals(1, $results->count());
        $result = $results->first();
        self::assertEquals(1, $result->count());
        self::assertEquals([
            ['x' => 'x'],
            [],
            ['x' => 'y'],
            [],
            ['x' => 'z'],
        ], $result->get('p'));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testPeriodicCommit(string $alias): void
    {
        if (getenv('TESTING_ENVIRONMENT') !== 'local') {
            self::markTestSkipped('Only local environment has access to local files');
        }

        $this->client->run(<<<CYPHER
USING PERIODIC COMMIT 10
LOAD CSV FROM 'file:///csv-example.csv' AS line
MERGE (n:File {name: line[0]});
CYPHER, [], $alias);

        $result = $this->client->run('MATCH (n:File) RETURN count(n) AS count');
        self::assertEquals(20, $result->first()->get('count'));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testPeriodicCommitFail(string $alias): void
    {
        if (getenv('TESTING_ENVIRONMENT') !== 'local') {
            self::markTestSkipped('Only local environment has access to local files');
        }

        if (str_starts_with($alias, 'http')) {
            self::markTestSkipped('HTTP allows periodic commits during an actual transaction');
        }

        $this->expectException(Neo4jException::class);

        $tsx = $this->client->beginTransaction([], $alias);
        $tsx->run(<<<CYPHER
USING PERIODIC COMMIT 10
LOAD CSV FROM 'file:///csv-example.csv' AS line
MERGE (n:File {name: line[0]});
CYPHER);
        $tsx->commit();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testLongQueryFunction(string $alias): void
    {
        $this->client->writeTransaction(static function (TransactionInterface $tsx) {
            $tsx->run('UNWIND range(1, 10000) AS x MERGE (:Number {value: x})');
        }, $alias, TransactionConfiguration::default()->withTimeout(100000));
        self::assertTrue(true);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testLongQueryFunctionNegative(string $alias): void
    {
        if (str_starts_with($alias, 'http')) {
            self::markTestSkipped('HTTP does not support tsx timeout at the moment.');
        }

        $this->expectException(ConnectException::class);
        $this->client->writeTransaction(static function (TransactionInterface $tsx) {
            $tsx->run('UNWIND range(1, 10000) AS x MERGE (:Number {value: x})');
        }, $alias, TransactionConfiguration::default()->withTimeout(1));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testLongQueryUnmanaged(string $alias): void
    {
        $tsx = $this->client->beginTransaction([], $alias, TransactionConfiguration::default()->withTimeout(100000));
        $tsx->run('UNWIND range(1, 10000) AS x MERGE (:Number {value: x})');
        self::assertTrue(true);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testLongQueryAuto(string $alias): void
    {
        $tsx = $this->client->beginTransaction([], $alias, TransactionConfiguration::default()->withTimeout(100000));
        $tsx->run('UNWIND range(1, 10000) AS x MERGE (:Number {value: x})');
        self::assertTrue(true);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testLongQueryUnmanagedNegative(string $alias): void
    {
        if (str_starts_with($alias, 'http')) {
            self::markTestSkipped('HTTP does not support tsx timeout at the moment.');
        }

        $this->expectException(ConnectException::class);
        $tsx = $this->client->beginTransaction([], $alias, TransactionConfiguration::default()->withTimeout(1));
        $tsx->run('UNWIND range(1, 10000) AS x MERGE (:Number {value: x})');
    }
}
