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
use Laudis\Neo4j\Formatter\BasicFormatter;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-import-type BasicResults from \Laudis\Neo4j\Formatter\BasicFormatter
 */
final class ConsistencyTest extends TestCase
{
    /** @var ClientInterface<BasicResults> */
    private ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = ClientBuilder::create()
            ->withDriver('http', 'http://neo4j:test@neo4j')
            ->withDriver('bolt', 'bolt://neo4j:test@neo4j')
            ->withDriver('cluster', 'neo4j://neo4j:test@core1')
            ->withFormatter(new BasicFormatter())
            ->build();
    }

    /**
     * @dataProvider aliases
     */
    public function testConsistency(string $alias): void
    {
        $this->client->run('MATCH (x) DETACH DELETE x', [], $alias);
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
        $this->client->run('MATCH (x) DETACH DELETE x', [], $alias);
        $tsx = $this->client->beginTransaction([
            Statement::create('CREATE (n:aaa) SET n.name="aaa" return n'),
        ], $alias);

        $tsx->run('CREATE (n:ccc) SET n.name="ccc"');

        $tsx->commit([Statement::create('CREATE (n:bbb) SET n.name="bbb" return n')]);

        $results = $this->client->run('MATCH (n) RETURN n ORDER BY n.name', [], $alias);

        self::assertEquals(3, $results->count());
        self::assertEquals(['name' => 'aaa'], $results->first()->get('n'));
        self::assertEquals(['name' => 'bbb'], $results->get(1)->get('n'));
        self::assertEquals(['name' => 'ccc'], $results->last()->get('n'));
    }

    /**
     * @return list<list<string>>
     */
    public function aliases(): array
    {
        return [
            ['http'],
            ['bolt'],
            ['cluster'],
        ];
    }
}
