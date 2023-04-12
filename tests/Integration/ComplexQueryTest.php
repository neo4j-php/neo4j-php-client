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

use Generator;

use function getenv;

use InvalidArgumentException;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Contracts\TransactionInterface as TSX;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\ParameterHelper;
use Laudis\Neo4j\Types\Node;

final class ComplexQueryTest extends EnvironmentAwareIntegrationTest
{
    public function testListParameterHelper(): void
    {
        $result = $this->getSession()->transaction(static fn (TSX $tsx) => $tsx->run('MATCH (x) WHERE x.slug IN $listOrMap RETURN x', [
            'listOrMap' => ParameterHelper::asList([]),
        ]));
        self::assertEquals(0, $result->count());
    }

    public function testValidListParameterHelper(): void
    {
        $result = $this->getSession()->transaction(static fn (TSX $tsx) => $tsx->run('RETURN $listOrMap AS x', [
            'listOrMap' => ParameterHelper::asList([1, 2, 3]),
        ]));
        self::assertEquals(1, $result->count());
        self::assertEquals([1, 2, 3], $result->first()->getAsArrayList('x')->toArray());
    }

    public function testMergeTransactionFunction(): void
    {
        $this->expectException(Neo4jException::class);
        $this->getSession()->writeTransaction(static fn (TSX $tsx) => /** @psalm-suppress ALL */
$tsx->run('MERGE (x {y: "z"}:X) return x')->first()
            ->getAsMap('x')
            ->getAsString('y'));
    }

    public function testValidMapParameterHelper(): void
    {
        $result = $this->getSession()->transaction(static fn (TSX $tsx) => $tsx->run('RETURN $listOrMap AS x', [
            'listOrMap' => ParameterHelper::asMap(['a' => 'b', 'c' => 'd']),
        ]));
        self::assertEquals(1, $result->count());
        self::assertEquals(['a' => 'b', 'c' => 'd'], $result->first()->getAsMap('x')->toArray());
    }

    public function testArrayParameterHelper(): void
    {
        $this->expectNotToPerformAssertions();
        $this->getSession()->transaction(static fn (TSX $tsx) => $tsx->run(<<<'CYPHER'
MERGE (x:Node {slug: 'a'})
WITH x
MATCH (x) WHERE x.slug IN $listOrMap RETURN x
CYPHER, ['listOrMap' => []]));
    }

    public function testInvalidParameter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->getSession()->transaction(fn (TSX $tsx) => $tsx->run(<<<'CYPHER'
MERGE (x:Node {slug: 'a'})
WITH x
MATCH (x) WHERE x.slug IN $listOrMap RETURN x
CYPHER, ['listOrMap' => self::generate()]));
    }

    private static function generate(): Generator
    {
        foreach (range(1, 3) as $x) {
            yield true => $x;
        }
    }

    public function testInvalidParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @var iterable<string, iterable<mixed, mixed>|scalar|null> $generator */
        $generator = self::generate();
        $this->getSession()->transaction(static fn (TSX $tsx) => $tsx->run(<<<'CYPHER'
MERGE (x:Node {slug: 'a'})
WITH x
MATCH (x) WHERE x.slug IN $listOrMap RETURN x
CYPHER, ['listOrMap' => $generator]));
    }

    public function testCreationAndResult(): void
    {
        $result = $this->getSession()->transaction(static fn (TSX $tsx) => $tsx->run('MERGE (x:Node {x:$x}) RETURN x', ['x' => 'x']))->first();

        self::assertEquals('x', $result->getAsNode('x')->getProperties()->get('x'));
    }

    public function testPath(): void
    {
        $results = $this->getSession(['bolt', 'neo4j'])->transaction(static fn (TSX $tsx) => $tsx->run(<<<'CYPHER'
MERGE (b:Node {x:$x}) - [:HasNode {attribute: $xy}] -> (:Node {y:$y}) - [:HasNode {attribute: $yz}] -> (:Node {z:$z})
WITH b
MATCH (x:Node) - [y:HasNode*2] -> (z:Node)
RETURN x, y, z
LIMIT 1
CYPHER, ['x' => 'x', 'xy' => 'xy', 'y' => 'y', 'yz' => 'yz', 'z' => 'z']));

        self::assertEquals(1, $results->count());
        $result = $results->first();
        self::assertEquals(3, $result->count());
        self::assertEquals('x', $result->getAsNode('x')->getProperty('x'));
        self::assertEquals(
            [['attribute' => 'xy'], ['attribute' => 'yz']],
            /** @psalm-suppress MissingClosureReturnType */
            $result->getAsCypherList('y')->map(static fn ($r) => /**
                 * @psalm-suppress MixedMethodCall
                 *
                 * @var array <string, string>
                 */
$r->getProperties()->toArray())->toArray()
        );
        self::assertEquals('z', $result->getAsNode('z')->getProperty('z'));
    }

    public function testNullListAndMap(): void
    {
        $results = $this->getSession()->transaction(static fn (TSX $tsx) => $tsx->run(<<<'CYPHER'
RETURN null AS x, [1, 2, 3] AS y, {x: 'x', y: 'y', z: 'z'} AS z
CYPHER, ['x' => 'x', 'xy' => 'xy', 'y' => 'y', 'yz' => 'yz', 'z' => 'z']));

        self::assertEquals(1, $results->count());
        $result = $results->first();
        self::assertEquals(3, $result->count());
        self::assertNull($result->get('x'));
        self::assertEquals([1, 2, 3], $result->getAsMap('y')->toArray());
        self::assertEquals(['x' => 'x', 'y' => 'y', 'z' => 'z'], $result->getAsMap('z')->toArray());
    }

    public function testListAndMapInput(): void
    {
        $results = $this->getSession()->transaction(static fn (TSX $tsx) => $tsx->run(<<<'CYPHER'
MERGE (x:Node {x: $x.x})
WITH x
MERGE (y:Node {list: $y})
RETURN x, y
LIMIT 1
CYPHER, ['x' => ['x' => 'x'], 'y' => [1, 2, 3]]));

        self::assertEquals(1, $results->count());
        $result = $results->first();
        self::assertEquals(2, $result->count());
        self::assertEquals('x', $result->getAsNode('x')->getProperty('x'));
        self::assertEquals(['list' => [1, 2, 3]], $result->getAsNode('y')->getProperties()->toRecursiveArray());
    }

    public function testPathReturnType(): void
    {
        $results = $this->getSession()->transaction(static function (TSX $tsx) {
            $tsx->run(<<<'CYPHER'
MERGE (:Node {x: 'x'}) - [:Rel] -> (x:Node {x: 'y'})
WITH x
MERGE (x) - [:Rel] -> (:Node {x: 'z'})
CYPHER, []);

            return $tsx->run(<<<'CYPHER'
MATCH (a:Node {x: 'x'}), (b:Node {x: 'z'}), p = shortestPath((a)-[*]-(b))
RETURN p
CYPHER);
        });

        self::assertEquals(1, $results->count());
        $result = $results->first();
        self::assertEquals(1, $result->count());
        self::assertEquals([
            ['x' => 'x'],
            ['x' => 'y'],
            ['x' => 'z'],
        ], $result->getAsPath('p')->getNodes()->map(static fn (Node $x) => /** @var array<string, string> */
$x->getProperties()->toArray())->toArray());
    }

    public function testPeriodicCommit(): void
    {
        if (getenv('TESTING_ENVIRONMENT') !== 'local') {
            self::markTestSkipped('Only local environment has access to local files');
        }

        $this->getSession()->run(<<<CYPHER
USING PERIODIC COMMIT 10
LOAD CSV FROM 'file:///csv-example.csv' AS line
MERGE (n:File {name: line[0]});
CYPHER, []);

        $result = $this->getSession()->run('MATCH (n:File) RETURN count(n) AS count');
        self::assertEquals(20, $result->first()->get('count'));
    }

    public function testPeriodicCommitFail(): void
    {
        if (getenv('TESTING_ENVIRONMENT') !== 'local') {
            self::markTestSkipped('Only local environment has access to local files');
        }

        $this->expectException(Neo4jException::class);

        $tsx = $this->getSession(['neo4j', 'bolt'])->beginTransaction([]);
        $tsx->run(<<<CYPHER
USING PERIODIC COMMIT 10
LOAD CSV FROM 'file:///csv-example.csv' AS line
MERGE (n:File {name: line[0]});
CYPHER
        );
        $tsx->commit();
    }

    public function testLongQueryFunction(): void
    {
        $this->expectNotToPerformAssertions();
        $this->getSession(['bolt', 'neo4j'])->writeTransaction(static function (TransactionInterface $tsx) {
            $tsx->run('CALL apoc.util.sleep(2)');
        }, TransactionConfiguration::default()->withTimeout(5));
    }

    public function testLongQueryFunctionNegative(): void
    {
        $this->expectException(Neo4jException::class);
        $this->getSession(['bolt', 'neo4j'])->writeTransaction(static function (TransactionInterface $tsx) {
            $tsx->run(<<<'CYPHER'
            UNWIND range(1, 10000) AS id
            MERGE (x:Node {id: id})
            CYPHER);
        }, TransactionConfiguration::default()->withTimeout(1));
    }

    public function testDiscardAfterTimeout(): void
    {
        try {
            $this->getSession(['bolt', 'neo4j'])
                ->run('CALL apoc.util.sleep(2000000) RETURN 5 as x', [], TransactionConfiguration::default()->withTimeout(2))
                ->first()
                ->get('x');
        } catch (Neo4jException $e) {
            self::assertEquals('Neo.ClientError.Transaction.TransactionTimedOut', $e->getNeo4jCode());
        }
    }

    public function testTimeoutNoReturn(): void
    {
        $result = $this->getSession(['bolt', 'neo4j'])
            ->run('CALL apoc.util.sleep(2000000)', [], TransactionConfiguration::default()->withTimeout(2));

        try {
            unset($result);
        } catch (Neo4jException $e) {
            $this->assertEquals('Neo.ClientError.Transaction.TransactionTimedOut', $e->getNeo4jCode());
        }
    }

    public function testTimeout(): void
    {
        $tsx = $this->getSession(['bolt', 'neo4j'])->beginTransaction([], TransactionConfiguration::default()->withTimeout(1));
        try {
            $tsx->run('UNWIND range(1, 10000) AS x MERGE (:Number {value: x})');
        } catch (Neo4jException $e) {
            self::assertEquals('Neo.ClientError.Transaction.TransactionTimedOut', $e->getNeo4jCode());
            $tsx = $this->getSession()->beginTransaction([], TransactionConfiguration::default()->withTimeout(20));
            self::assertEquals(1, $tsx->run('RETURN 1 AS one')->first()->get('one'));
        }
    }

    public function testConstraintHandling(): void
    {
        $session = $this->getSession();

        $session->run('MATCH (test:Test{id: \'123\'}) DETACH DELETE test');
        $session->run("CREATE (test:Test{id: '123'})");

        $session->run('CREATE CONSTRAINT IF NOT EXISTS FOR (test:Test) REQUIRE test.id IS UNIQUE');

        $this->expectException(Neo4jException::class);
        $session->run("CREATE (test:Test {id: '123'}) RETURN test");
    }

    public function testFetchSize(): void
    {
        $client = $this->getSession();

        // Confirm that the database contains 4000 unique user nodes
        $userCountResults = $client->run('RETURN 4000 as user_count');
        $userCount = $userCountResults->getAsMap(0)->getAsInt('user_count');

        $this->assertEquals(4000, $userCount);

        // Retrieve the ids of all user nodes
        $results = $client->run('UNWIND range(1, 4000) AS id RETURN id', []);

        // Loop through the results and add each id to an array
        $userIds = [];
        foreach ($results as $result) {
            $userIds[] = $result->get('id');
        }

        $this->assertCount(4000, $userIds);

        // Check if we have any duplicate ids by removing duplicate values
        // from the array.
        $uniqueUserIds = array_unique($userIds);

        $this->assertEquals($userIds, $uniqueUserIds);
    }

    public function testLongQueryUnmanagedNegative(): void
    {
        try {
            $tsx = $this->getSession(['bolt', 'neo4j'])->beginTransaction([], TransactionConfiguration::default()->withTimeout(1));
            $tsx->run('UNWIND range(1, 10000) AS x MERGE (:Number {value: x})');
        } catch (Neo4jException $e) {
            self::assertEquals('Neo.ClientError.Transaction.TransactionTimedOut', $e->getNeo4jCode());
        }
    }

    public function testReturnNoResults(): void
    {
        self::assertEquals([], $this->getSession()->run('UNWIND [] AS x RETURN x')->toRecursiveArray());
    }
}
