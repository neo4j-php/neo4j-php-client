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

use Ds\Map;
use Ds\Vector;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Laudis\Neo4j\Bolt\BoltConfiguration;
use PHPUnit\Framework\TestCase;

final class OGMFormatterIntegrationTest extends TestCase
{
    /** @var ClientInterface<Vector<Map<string, mixed>>> */
    private ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = ClientBuilder::create()
            ->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j-42')
            ->addHttpConnection('http', 'http://neo4j:test@neo4j-42')
            ->addBoltConnection('cluster', 'http://neo4j:test@core1', BoltConfiguration::create()->withAutoRouting(true))
            ->withFormatter(new OGMFormatter())
            ->build();
    }

//    /**
//     * @dataProvider transactionProvider
//     */
//    public function testPath(string $alias): void
//    {
//        $results = $this->client->run(<<<'CYPHER'
    //MERGE (b:Node {x:$x}) - [:HasNode {attribute: $xy}] -> (:Node {y:$y}) - [:HasNode {attribute: $yz}] -> (:Node {z:$z})
    //WITH b
    //MATCH (x:Node) - [y:HasNode*2] -> (z:Node)
    //RETURN x, y, z
    //CYPHER
//            , ['x' => 'x', 'xy' => 'xy', 'y' => 'y', 'yz' => 'yz', 'z' => 'z'], $alias);
//
//        self::assertEquals(1, $results->count());
//    }

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
