<?php
declare(strict_types=1);


namespace Laudis\Neo4j\Tests\Integration;


use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use PHPUnit\Framework\TestCase;

final class HttpConsistencyTest extends TestCase
{
    private ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = ClientBuilder::create()
            ->addHttpConnection('default', 'http://neo4j:test@neo4j')
            ->setDefaultConnection('default')
            ->build();
    }

    public function testConsistency(): void
    {
        $res = $this->client->run('MERGE (n:zzz {name: "bbbb"}) RETURN n');
        self::assertEquals(1, $res->count());
        self::assertEquals(['name' => 'bbbb'], $res->first()->get('n'));

        $res = $this->client->run('MATCH (n:zzz {name: $name}) RETURN n', ['name' => 'bbbb']);
        self::assertEquals(1, $res->count());
        self::assertEquals(['name' => 'bbbb'], $res->first()->get('n'));
    }
}
