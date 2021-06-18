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

use Generator;
use InvalidArgumentException;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Formatter\BasicFormatter;
use Laudis\Neo4j\ParameterHelper;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-import-type BasicResults from \Laudis\Neo4j\Formatter\BasicFormatter
 */
final class ComplexQueryTests extends TestCase
{
    /** @var ClientInterface<BasicResults> */
    private ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = ClientBuilder::create()
            ->withDriver('bolt', 'bolt://neo4j:test@neo4j')
            ->withDriver('http', 'http://neo4j:test@neo4j')
            ->withDriver('cluster', 'neo4j://neo4j:test@core1')
            ->withFormatter(new BasicFormatter())
            ->build();
    }

    /**
     * @dataProvider transactionProvider
     */
    public function testListParameterHelper(string $alias): void
    {
        $result = $this->client->run(<<<'CYPHER'
MATCH (x) WHERE x.slug IN $listOrMap RETURN x
CYPHER, ['listOrMap' => ParameterHelper::asList([])], $alias);
        self::assertEquals(0, $result->count());
    }

    /**
     * @dataProvider transactionProvider
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
     * @dataProvider transactionProvider
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
     * @dataProvider transactionProvider
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
     * @dataProvider transactionProvider
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
     * @dataProvider transactionProvider
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
     * @dataProvider transactionProvider
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
     * @dataProvider transactionProvider
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
     * @dataProvider transactionProvider
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
     * @dataProvider transactionProvider
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
     * @dataProvider transactionProvider
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
     * @return array<int, array<int, string>>
     */
    public function transactionProvider(): array
    {
        return [
            ['http'],
            ['bolt'],
            ['cluster'],
        ];
    }
}
