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

use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use PHPUnit\Framework\TestCase;

final class ComplexQueryTests extends TestCase
{
    private ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = ClientBuilder::create()
            ->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j')
            ->addHttpConnection('http', 'http://neo4j:test@neo4j')
            ->build();
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
RETURN null as x, [1, 2, 3] as y, {x: 'x', y: 'y', z: 'z'} as z
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
     * @return array<int, array<int, string>>
     */
    public function transactionProvider(): array
    {
        return [
            ['http'],
            ['bolt'],
        ];
    }
}
