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

use InvalidArgumentException;
use Laudis\Neo4j\Bolt\BoltConfiguration;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Contracts\TransactionInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\BasicFormatter;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-import-type BasicResults from \Laudis\Neo4j\Formatter\BasicFormatter
 */
final class ClientIntegrationTest extends TestCase
{
    /** @var ClientInterface<BasicResults> */
    private ClientInterface $client;

    /**
     * @return non-empty-array<array-key, array{0: string}>
     */
    public function connectionAliases(): iterable
    {
        return [['bolt'], ['http'], ['cluster']];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = $this->createClient();
    }

    /**
     * @return ClientInterface<BasicResults>
     */
    public function createClient(): ClientInterface
    {
        return ClientBuilder::create()
            ->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j')
            ->addHttpConnection('http', 'http://neo4j:test@neo4j')
            ->addBoltConnection('cluster', 'bolt://neo4j:test@core1', BoltConfiguration::create()->withAutoRouting(true))
            ->withFormatter(new BasicFormatter())
            ->build();
    }

    public function testEqualEffect(): void
    {
        $statement = new Statement(
            'merge(u:User{email: $email}) on create set u.uuid=$uuid return u',
            ['email' => 'a@b.c', 'uuid' => 'cc60fd69-a92b-47f3-9674-2f27f3437d66']
        );

        $x = $this->client->runStatement($statement, 'bolt');
        $y = $this->client->runStatement($statement, 'http');

        self::assertEquals($x, $y);
        self::assertEquals($x->toArray(), $y->toArray());
    }

    public function testAvailabilityFullImplementation(): void
    {
        $results = $this->client->getDriver('cluster')
            ->createSession()
            ->beginTransaction()
            ->run('UNWIND [1] AS x RETURN x')
            ->first()
            ->get('x');

        self::assertEquals(1, $results);
    }

    public function testTransactionFunction(): void
    {
        $result = $this->client->transaction(static function (TransactionInterface $tsx) {
            return $tsx->run('UNWIND [1] AS x RETURN x')->first()->get('x');
        });

        self::assertEquals(1, $result);

        $result = $this->client->readTransaction(static function (TransactionInterface $tsx) {
            return $tsx->run('UNWIND [1] AS x RETURN x')->first()->get('x');
        });

        self::assertEquals(1, $result);

        $result = $this->client->writeTransaction(static function (TransactionInterface $tsx) {
            return $tsx->run('UNWIND [1] AS x RETURN x')->first()->get('x');
        });

        self::assertEquals(1, $result);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testValidRun(string $alias): void
    {
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

    /**
     * @dataProvider connectionAliases
     */
    public function testInvalidRun(string $alias): void
    {
        $exception = false;
        try {
            $this->client->run('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b'], $alias);
        } catch (Neo4jException $e) {
            $exception = true;
        }
        self::assertTrue($exception);
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testValidStatement(string $alias): void
    {
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

    /**
     * @dataProvider connectionAliases
     */
    public function testInvalidStatement(string $alias): void
    {
        $exception = false;
        try {
            $statement = Statement::create('MERGE (x:Tes0342hdm21.())', ['test' => 'a', 'otherTest' => 'b']);
            $this->client->runStatement($statement, $alias);
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

    /**
     * @dataProvider connectionAliases
     */
    public function testInvalidStatements(string $alias): void
    {
        $this->expectException(Neo4jException::class);
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
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testMultipleTransactions(string $alias): void
    {
        $x = $this->client->beginTransaction(null, $alias);
        $y = $this->client->beginTransaction(null, $alias);
        self::assertNotSame($x, $y);
        $x->rollback();
        $y->rollback();
    }

    public function testInvalidConnection(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The provided alias: "ghqkneq;tr" was not found in the connection pool');

        $this->client->run('RETURN 1 AS x', [], 'ghqkneq;tr');
    }
}
