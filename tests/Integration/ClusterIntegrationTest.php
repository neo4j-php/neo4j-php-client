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

use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Contracts\ClientInterface;
use Laudis\Neo4j\Network\Bolt\BoltInjections;
use PHPUnit\Framework\TestCase;

final class ClusterIntegrationTest extends TestCase
{
    private ClientInterface $client;

    protected function setUp(): void
    {
        parent::setUp();
        $injections = BoltInjections::create()->withAutoRouting(true);
        $this->client = ClientBuilder::create()
            ->addBoltConnection('cluster', 'bolt://neo4j:test@core1', $injections)
            ->build();
    }

    public function testAcceptance(): void
    {
        self::assertEquals(1, $this->client->run('RETURN 1 as x')->first()->get('x'));
    }

    public function testWrite(): void
    {
        self::assertEquals([], $this->client->run('MERGE (x:X) RETURN x')->first()->get('x'));
    }
}
