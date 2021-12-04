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

use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Contracts\TransactionInterface as TSX;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Formatter\BasicFormatter;
use function str_starts_with;

/**
 * @psalm-import-type BasicResults from \Laudis\Neo4j\Formatter\BasicFormatter
 *
 * @extends EnvironmentAwareIntegrationTest<BasicResults>
 */
final class ConsistencyTest extends EnvironmentAwareIntegrationTest
{
    protected static function formatter(): FormatterInterface
    {
        /** @psalm-suppress InvalidReturnStatement */
        return new BasicFormatter();
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testConsistency(string $alias): void
    {
        $res = $this->getClient()->transaction(function (TSX $tsx) {
            $tsx->run('MATCH (x) DETACH DELETE x', []);
            $res = $tsx->run('MERGE (n:zzz {name: "bbbb"}) RETURN n');
            self::assertEquals(1, $res->count());
            self::assertEquals(['name' => 'bbbb'], $res->first()->get('n'));

            return $tsx->run('MATCH (n:zzz {name: $name}) RETURN n', ['name' => 'bbbb']);
        }, $alias);

        self::assertEquals(1, $res->count());
        self::assertEquals(['name' => 'bbbb'], $res->first()->get('n'));
    }

    /**
     * @dataProvider connectionAliases
     */
    public function testConsistencyTransaction(string $alias): void
    {
        if (str_starts_with($alias, 'neo4j')) {
            self::markTestSkipped('Cannot guarantee successful test in cluster');
        }

        $this->getClient()->run('MATCH (x) DETACH DELETE x', [], $alias);
        $tsx = $this->getClient()->beginTransaction([
            Statement::create('CREATE (n:aaa) SET n.name="aaa" return n'),
        ], $alias);

        $tsx->run('CREATE (n:ccc) SET n.name="ccc"');

        $tsx->commit([Statement::create('CREATE (n:bbb) SET n.name="bbb" return n')]);

        $results = $this->getClient()->run('MATCH (n) RETURN n ORDER BY n.name', [], $alias);

        self::assertEquals(3, $results->count());
        self::assertEquals(['name' => 'aaa'], $results->first()->get('n'));
        self::assertEquals(['name' => 'bbb'], $results->get(1)->get('n'));
        self::assertEquals(['name' => 'ccc'], $results->last()->get('n'));
    }
}
