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
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Network\Bolt\BoltInjections;
use PHPUnit\Framework\TestCase;

final class HttpConsistencyTest extends TestCase
{
    private ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = ClientBuilder::create()
            ->addHttpConnection('http', 'http://neo4j:test@neo4j')
            ->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j')
            ->addBoltConnection('neo4j', 'bolt://neo4j:test@neo4j', BoltInjections::create()->withAutoRouting(true))
            ->build();

        $this->client->run('MATCH (x) DETACH DELETE x');
    }

    /**
     * @dataProvider aliases
     */
    public function testConsistency(string $alias): void
    {
        $res = $this->client->run('MERGE (n:zzz {name: "bbbb"}) RETURN n', [], $alias);
        self::assertEquals(1, $res->count());
        self::assertEquals(['name' => 'bbbb'], $res->first()->get('n'));

        $res = $this->client->run('MATCH (n:zzz {name: $name}) RETURN n', ['name' => 'bbbb'], $alias);
        self::assertEquals(1, $res->count());
        self::assertEquals(['name' => 'bbbb'], $res->first()->get('n'));
    }

    /**
     * @dataProvider aliases
     */
    public function testConsistencyTransaction(string $alias): void
    {
        $tsx = $this->client->openTransaction([
            Statement::create('CREATE (n:aaa) SET n.name="aaa" return n'),
        ], $alias);

        $tsx->run('CREATE (n:ccc) SET n.name="ccc"');

        $tsx->commit([Statement::create('CREATE (n:bbb) SET n.name="bbb" return n')]);

        $results = $this->client->run('MATCH (n) RETURN n', ['name' => 'bbbb'], $alias);

        self::assertEquals(3, $results->count());
        self::assertEquals(['name' => 'aaa'], $results->first()->get('n'));
        self::assertEquals(['name' => 'ccc'], $results->get(1)->get('n'));
        self::assertEquals(['name' => 'bbb'], $results->last()->get('n'));
    }

    /**
     * @return list<list<string>>
     */
    public function aliases(): array
    {
        return [
            ['http'],
            ['bolt'],
            ['neo4j'],
        ];
    }
}
