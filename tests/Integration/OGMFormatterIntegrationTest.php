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
use Laudis\Neo4j\Types\Node;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Formatter\OGMFormatter;
use Laudis\Neo4j\Network\Bolt\BoltConfig;
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
            ->addBoltConnection('cluster', 'http://neo4j:test@core1', BoltConfig::create()->withAutoRouting(true))
            ->setFormatter(new OGMFormatter())
            ->build();
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
    }

    /**
     * @dataProvider transactionProvider
     */
    public function testNode(string $alias): void
    {
        $uuid = 'cc60fd69-a92b-47f3-9674-2f27f3437d66';
        $email = 'a@b.c';
        $type = 'pepperoni';

        $results = $this->client->run(
            'MERGE (u:User{email: $email})-[:LIKES]->(p:Food:Pizza {type: $type}) ON CREATE SET u.uuid=$uuid RETURN u, p',
            ['email' => $email, 'uuid' => $uuid, 'type' => $type], $alias
        );

        self::assertEquals(1, $results->count());

        /** @var Node $u */
        $u = $results[0]['u'];
        self::assertInstanceOf(Node::class, $u);
        self::assertEquals(['User'], $u->labels());
        self::assertEquals($email, $u->properties()['email']);
        self::assertEquals($uuid, $u->properties()['uuid']);

        /** @var Node $p */
        $p = $results[0]['p'];
        self::assertInstanceOf(Node::class, $p);
        self::assertEquals(['Food', 'Pizza'], $p->labels());
        self::assertEquals($type, $p->properties()['type']);
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
