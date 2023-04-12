<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Tests\Integration;

use Laudis\Neo4j\Contracts\TransactionInterface as TSX;
use Laudis\Neo4j\Databags\Statement;

final class ConsistencyTest extends EnvironmentAwareIntegrationTest
{
    public function testConsistency(): void
    {
        $res = $this->getSession()->transaction(function (TSX $tsx) {
            $res = $tsx->run('MERGE (n:zzz {name: "bbbb"}) RETURN n');
            self::assertEquals(1, $res->count());
            self::assertEquals(['name' => 'bbbb'], $res->first()->getAsNode('n')->getProperties()->toArray());

            return $tsx->run('MATCH (n:zzz {name: $name}) RETURN n', ['name' => 'bbbb']);
        });

        self::assertEquals(1, $res->count());
        self::assertEquals(['name' => 'bbbb'], $res->first()->getAsNode('n')->getProperties()->toArray());
    }

    public function testConsistencyTransaction(): void
    {
        $tsx = $this->getSession()->beginTransaction([
            Statement::create('MERGE (n:aaa) SET n.name="aaa" return n'),
        ]);

        $tsx->run('MERGE (n:ccc) SET n.name="ccc"');

        $tsx->commit([Statement::create('MERGE (n:bbb) SET n.name="bbb" return n')]);

        $results = $this->getSession()->run('MATCH (n) WHERE n.name = "aaa" OR n.name = "bbb" OR n.name = "ccc" RETURN n ORDER BY n.name');

        self::assertEquals(3, $results->count());
        self::assertEquals(['name' => 'aaa'], $results->first()->getAsNode('n')->getProperties()->toArray());
        self::assertEquals(['name' => 'bbb'], $results->get(1)->getAsNode('n')->getProperties()->toArray());
        self::assertEquals(['name' => 'ccc'], $results->last()->getAsNode('n')->getProperties()->toArray());
    }
}
