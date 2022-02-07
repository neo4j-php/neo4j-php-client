<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Tests\Integration;

use Dotenv\Dotenv;
use function explode;
use function is_string;
use Laudis\Neo4j\Basic\Driver;
use Laudis\Neo4j\Types\CypherMap;
use PHPUnit\Framework\TestCase;

final class BasicDriverTest extends TestCase
{
    /**
     * @return list<array{0: string}>
     */
    public function getConnections(): array
    {
        /** @var string|mixed $connections */
        $connections = $_ENV['NEO4J_CONNECTIONS'] ?? false;
        if (!is_string($connections)) {
            Dotenv::createImmutable(__DIR__.'/../../')->load();
            /** @var string|mixed $connections */
            $connections = $_ENV['NEO4J_CONNECTIONS'] ?? false;
            if (!is_string($connections)) {
                $connections = 'bolt://neo4j:test@neo4j,neo4j://neo4j:test@core1,http://neo4j:test@neo4j';
            }
        }

        $tbr = [];
        foreach (explode(',', $connections) as $connection) {
            $tbr[] = [$connection];
        }

        return $tbr;
    }

    /**
     * @dataProvider getConnections
     */
    public function testFullWalk(string $connection): void
    {
        $driver = Driver::create($connection);

        $session = $driver->createSession();

        $session->run('MATCH (x) DETACH DELETE x');
        $session->run('CREATE (x:X {id: 0})');

        $id = 1;
        $result = $session->run('MATCH (x) RETURN x');
        $result->each(static function (CypherMap $map) use (&$id) {
            /** @psalm-suppress all */
            $id = $map->getAsNode('x')->getProperties()->getAsInt('id');
        });

        self::assertEquals(0, $id);
    }

    public function testMap(string $connection): void
    {
        $result = Driver::create($connection)->createSession()->run(<<<'CYPHER'
        MATCH (id:GraphNode)
        WHERE id.end >= $start AND id.start <= $end
        RETURN id.content AS content
        CYPHER, ['start' => 0, 'end' => 86400]);

        self::assertCount(4, $result);
        self::assertEqualsCanonicalizing([
            'graphContent', 'aContent', 'cContent', '',
        ], $result->map(static fn (CypherMap $map) => $map->getAsString('content'))->toArray());
    }
}
