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

use function array_pop;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Formatter\BasicFormatter;

/**
 * @psalm-import-type BasicResults from \Laudis\Neo4j\Formatter\BasicFormatter
 *
 * @extends EnvironmentAwareIntegrationTest<BasicResults>
 */
final class ConsistencyTest extends EnvironmentAwareIntegrationTest
{
    protected function formatter(): FormatterInterface
    {
        /** @psalm-suppress InvalidReturnStatement */
        return new BasicFormatter();
    }

    /**
     * @dataProvider connectionAliases
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
     * @dataProvider connectionAliases
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

    public function testConsistencyMultiple(): void
    {
        $aliases = $this->connectionAliases();
        $tsxs = [];
        for ($i = 0; $i < 1000; ++$i) {
            $alias = $aliases[$i % count($aliases)][0];
            if ($i % 2 === 0) {
                $tsx = $this->client->beginTransaction(null, $alias);
                $x = $tsx->run('RETURN 1 AS x')->first()->get('x');
                $tsxs[] = $tsx;
            } else {
                $x = $this->client->run('RETURN 1 AS x', [], $alias)->first()->get('x');
            }

            self::assertEquals(1, $x);
            if ($i % 50 === 9) {
                for ($j = 0; $j < 24; ++$j) {
                    array_pop($tsxs);
                }
            }
        }
    }
}
