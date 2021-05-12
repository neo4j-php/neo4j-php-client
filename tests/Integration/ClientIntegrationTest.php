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
use Laudis\Neo4j\Tests\Base\ClientTest;

final class ClientIntegrationTest extends ClientTest
{
    public function createClient(): ClientInterface
    {
        $builder = ClientBuilder::create();
        $builder->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j');
        $builder->addBoltConnection('http', 'http://neo4j:test@neo4j');
        $builder->addBoltConnection('cluster', 'bolt://neo4j:test@core1', BoltInjections::create()->withAutoRouting(true));

        return $builder->build();
    }

    public function testEqualEffect(): void
    {
        $statement = new Statement(
            'merge(u:User{email: $email}) on create set u.uuid=$uuid return u',
            ['email' => 'a@b.c', 'uuid' => 'cc60fd69-a92b-47f3-9674-2f27f3437d66']
        );

        $x = $this->client->runStatement($statement, 'bolt');
        $y = $this->client->runStatement($statement, 'http');

        self::assertEquals($x, $y);
        self::assertEquals($x->toArray(), $y->toArray());
    }
}
