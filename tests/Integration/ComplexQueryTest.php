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

use Bolt\error\ConnectionTimeoutException;
use Bolt\error\MessageException;
use Generator;
use function getenv;
use InvalidArgumentException;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface as TSX;
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\ParameterHelper;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Node;
use function str_starts_with;

/**
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @extends EnvironmentAwareIntegrationTest<SummarizedResult<CypherMap<OGMTypes>>>
 */
final class ComplexQueryTest extends EnvironmentAwareIntegrationTest
{
    protected static function formatter(): FormatterInterface
    {
        return SummarizedResultFormatter::create();
    }

    protected static function createClient(): ClientInterface
    {
        $connections = self::buildConnections();

        $builder = ClientBuilder::create();
        foreach ($connections as $i => $connection) {
            $uri = Uri::create($connection);
            $builder = $builder->withDriver($uri->getScheme().'_'.$i, $connection);
        }

        /** @psalm-suppress InvalidReturnStatement */
        return $builder->withFormatter(self::formatter())->build();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testListParameterHelper(string $alias): void
    {
        $result = $this->getClient()->transaction(static function (TSX $tsx) {
            return $tsx->run('MATCH (x) WHERE x.slug IN $listOrMap RETURN x', [
                'listOrMap' => ParameterHelper::asList([]),
            ]);
        }, $alias);
        self::assertEquals(0, $result->count());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testValidListParameterHelper(string $alias): void
    {
        $result = $this->getClient()->transaction(static function (TSX $tsx) {
            return $tsx->run('RETURN $listOrMap AS x', [
                'listOrMap' => ParameterHelper::asList([1, 2, 3]),
            ]);
        }, $alias);
        self::assertEquals(1, $result->count());
        self::assertEquals([1, 2, 3], $result->first()->getAsArrayList('x')->toArray());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testMergeTransactionFunction(string $alias): void
    {
        $this->expectException(Neo4jException::class);
        $this->getClient()->writeTransaction(static function (TSX $tsx) {
            /** @psalm-suppress ALL */
            return $tsx->run('MERGE (x {y: "z"}:X) return x')->first()
                ->getAsMap('x')
                ->getAsString('y');
        }, $alias);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testValidMapParameterHelper(string $alias): void
    {
        $result = $this->getClient()->transaction(static function (TSX $tsx) {
            return $tsx->run('RETURN $listOrMap AS x', [
                'listOrMap' => ParameterHelper::asMap(['a' => 'b', 'c' => 'd']),
            ]);
        }, $alias);
        self::assertEquals(1, $result->count());
        self::assertEquals(['a' => 'b', 'c' => 'd'], $result->first()->getAsMap('x')->toArray());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testArrayParameterHelper(string $alias): void
    {
        $this->expectNotToPerformAssertions();
        $this->getClient()->transaction(static function (TSX $tsx) {
            return $tsx->run(<<<'CYPHER'
MERGE (x:Node {slug: 'a'})
WITH x
MATCH (x) WHERE x.slug IN $listOrMap RETURN x
CYPHER, ['listOrMap' => []]);
        }, $alias);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testInvalidParameter(string $alias): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->getClient()->transaction(function (TSX $tsx) {
            return $tsx->run(<<<'CYPHER'
MERGE (x:Node {slug: 'a'})
WITH x
MATCH (x) WHERE x.slug IN $listOrMap RETURN x
CYPHER, ['listOrMap' => self::generate()]);
        }, $alias);
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
        $this->getClient()->transaction(static function (TSX $tsx) use ($generator) {
            return $tsx->run(<<<'CYPHER'
MERGE (x:Node {slug: 'a'})
WITH x
MATCH (x) WHERE x.slug IN $listOrMap RETURN x
CYPHER, ['listOrMap' => $generator]);
        }, $alias);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testCreationAndResult(string $alias): void
    {
        $result = $this->getClient()->transaction(static function (TSX $tsx) {
            return $tsx->run('MERGE (x:Node {x:$x}) RETURN x', ['x' => 'x']);
        }, $alias)->first();

        self::assertEquals(['x' => 'x'], $result->getAsNode('x')->getProperties()->toArray());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testPath(string $alias): void
    {
        if (str_starts_with($alias, 'http')) {
            self::markTestSkipped('Http cannot detected nested attributes');
        }

        $results = $this->getClient()->transaction(static function (TSX $tsx) {
            return $tsx->run(<<<'CYPHER'
MERGE (b:Node {x:$x}) - [:HasNode {attribute: $xy}] -> (:Node {y:$y}) - [:HasNode {attribute: $yz}] -> (:Node {z:$z})
WITH b
MATCH (x:Node) - [y:HasNode*2] -> (z:Node)
RETURN x, y, z
CYPHER, ['x' => 'x', 'xy' => 'xy', 'y' => 'y', 'yz' => 'yz', 'z' => 'z']);
        }, $alias);

        self::assertEquals(1, $results->count());
        $result = $results->first();
        self::assertEquals(3, $result->count());
        self::assertEquals(['x' => 'x'], $result->getAsNode('x')->getProperties()->toArray());
        self::assertEquals(
            [['attribute' => 'xy'], ['attribute' => 'yz']],
            /** @psalm-suppress MissingClosureReturnType */
            $result->getAsCypherList('y')->map(static function ($r) {
                /**
                 * @psalm-suppress MixedMethodCall
                 *
                 * @var array <string, string>
                 */
                return $r->getProperties()->toArray();
            })->toArray()
        );
        self::assertEquals(['z' => 'z'], $result->getAsNode('z')->getProperties()->toArray());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testNullListAndMap(string $alias): void
    {
        $results = $this->getClient()->transaction(static function (TSX $tsx) {
            return $tsx->run(<<<'CYPHER'
RETURN null AS x, [1, 2, 3] AS y, {x: 'x', y: 'y', z: 'z'} AS z
CYPHER, ['x' => 'x', 'xy' => 'xy', 'y' => 'y', 'yz' => 'yz', 'z' => 'z']);
        }, $alias);

        self::assertEquals(1, $results->count());
        $result = $results->first();
        self::assertEquals(3, $result->count());
        self::assertNull($result->get('x'));
        self::assertEquals([1, 2, 3], $result->getAsMap('y')->toArray());
        self::assertEquals(['x' => 'x', 'y' => 'y', 'z' => 'z'], $result->getAsMap('z')->toArray());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testListAndMapInput(string $alias): void
    {
        $results = $this->getClient()->transaction(static function (TSX $tsx) {
            return $tsx->run(<<<'CYPHER'
MERGE (x:Node {x: $x.x})
WITH x
MERGE (y:Node {list: $y})
RETURN x, y
LIMIT 1
CYPHER, ['x' => ['x' => 'x'], 'y' => [1, 2, 3]]);
        }, $alias);

        self::assertEquals(1, $results->count());
        $result = $results->first();
        self::assertEquals(2, $result->count());
        self::assertEquals(['x' => 'x'], $result->getAsNode('x')->getProperties()->toArray());
        self::assertEquals(['list' => [1, 2, 3]], $result->getAsNode('y')->getProperties()->toRecursiveArray());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testPathReturnType(string $alias): void
    {
        $results = $this->getClient()->transaction(static function (TSX $tsx) {
            $tsx->run(<<<'CYPHER'
MERGE (:Node {x: 'x'}) - [:Rel] -> (x:Node {x: 'y'})
WITH x
MERGE (x) - [:Rel] -> (:Node {x: 'z'})
CYPHER, []);

            return $tsx->run(<<<'CYPHER'
MATCH (a:Node {x: 'x'}), (b:Node {x: 'z'}), p = shortestPath((a)-[*]-(b))
RETURN p
CYPHER);
        }, $alias);

        self::assertEquals(1, $results->count());
        $result = $results->first();
        self::assertEquals(1, $result->count());
        self::assertEquals([
            ['x' => 'x'],
            ['x' => 'y'],
            ['x' => 'z'],
        ], $result->getAsPath('p')->getNodes()->map(static function (Node $x) {
            /** @var array<string, string> */
            return $x->getProperties()->toArray();
        })->toArray());
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testPeriodicCommit(string $alias): void
    {
        if (getenv('TESTING_ENVIRONMENT') !== 'local') {
            self::markTestSkipped('Only local environment has access to local files');
        }

        $this->getClient()->run(<<<CYPHER
USING PERIODIC COMMIT 10
LOAD CSV FROM 'file:///csv-example.csv' AS line
MERGE (n:File {name: line[0]});
CYPHER, [], $alias);

        $result = $this->getClient()->run('MATCH (n:File) RETURN count(n) AS count');
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

        $tsx = $this->getClient()->beginTransaction([], $alias);
        $tsx->run(<<<CYPHER
USING PERIODIC COMMIT 10
LOAD CSV FROM 'file:///csv-example.csv' AS line
MERGE (n:File {name: line[0]});
CYPHER
        );
        $tsx->commit();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testLongQueryFunction(string $alias): void
    {
        if (str_starts_with($alias, 'http')) {
            self::markTestSkipped('Http does not support timeouts at the moment');
        }

        $this->expectNotToPerformAssertions();
        $this->getClient()->writeTransaction(static function (TransactionInterface $tsx) {
            $tsx->run('CALL apoc.util.sleep(20000)');
        }, $alias, TransactionConfiguration::default()->withTimeout(100000));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testLongQueryFunctionNegative(string $alias): void
    {
        if (str_starts_with($alias, 'http')) {
            self::markTestSkipped('Http does not support timeouts at the moment');
        }

        $this->expectException(ConnectionTimeoutException::class);
        $this->getClient()->writeTransaction(static function (TransactionInterface $tsx) {
            $tsx->run('CALL apoc.util.sleep(10000)');
        }, $alias, TransactionConfiguration::default()->withTimeout(1));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testLongQueryUnmanaged(string $alias): void
    {
        if (str_starts_with($alias, 'http')) {
            self::markTestSkipped('Http does not support timeouts at the moment');
        }
        $this->expectException(ConnectionTimeoutException::class);
        $tsx = $this->getClient()->beginTransaction([], $alias, TransactionConfiguration::default()->withTimeout(1));
        $tsx->run('CALL apoc.util.sleep(10000)');
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testTimeout(string $alias): void
    {
        if (str_starts_with($alias, 'http')) {
            self::markTestSkipped('Http does not support timeouts at the moment');
        }

        try {
            $tsx = $this->getClient()->beginTransaction([], $alias, TransactionConfiguration::default()->withTimeout(1));
            $tsx->run('CALL apoc.util.sleep(10000)');
        } catch (ConnectionTimeoutException $e) {
            $tsx = $this->getClient()->beginTransaction([], $alias, TransactionConfiguration::default()->withTimeout(20));
            self::assertEquals(1, $tsx->run('RETURN 1 AS one')->first()->get('one'));
        }
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testTimeoutRecovery(string $alias): void
    {
        if (str_starts_with($alias, 'http')) {
            self::markTestSkipped('Http does not support timeouts at the moment');
        }

        $this->expectNotToPerformAssertions();
        $tsx = $this->getClient()->beginTransaction([], $alias, TransactionConfiguration::default()->withTimeout(1000));
        $tsx->run('CALL apoc.util.sleep(20000)');
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testLongQueryUnmanagedNegative(string $alias): void
    {
        if (str_starts_with($alias, 'http')) {
            self::markTestSkipped('HTTP does not support tsx timeout at the moment.');
        }

        $this->expectException(MessageException::class);
        $tsx = $this->getClient()->beginTransaction([], $alias, TransactionConfiguration::default()->withTimeout(1));
        $tsx->run('UNWIND range(1, 10000) AS x MERGE (:Number {value: x})');
    }
}
