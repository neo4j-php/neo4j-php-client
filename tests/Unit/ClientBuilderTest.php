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

namespace Laudis\Neo4j\Tests\Unit;

use BadMethodCallException;
use Buzz\Exception\NetworkException;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Network\Bolt\BoltConfig;
use Laudis\Neo4j\Network\Http\HttpConfig;
use PHPUnit\Framework\TestCase;

final class ClientBuilderTest extends TestCase
{
    public function testEmpty(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Client cannot be built with an empty driver pool');
        ClientBuilder::create()->build();
    }

    public function testBadDefault(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Client cannot be built with a default connection "error" that is not in the driver pool');

        ClientBuilder::create()
            ->addHttpConnection('temp', 'http://neoj:test@localhost')
            ->setDefaultConnection('error')
            ->build();
    }

    public function testBoltSetupWithScheme(): void
    {
        $client = ClientBuilder::create()->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j-42:7687')->build();
        $tsx = $client->openTransaction();
        self::assertTrue(true);
        $tsx->rollback();
    }

    public function testBoltSetupWithoutPort(): void
    {
        $client = ClientBuilder::create()->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j-42')->build();
        $tsx = $client->openTransaction();
        self::assertTrue(true);
        $tsx->rollback();
    }

    public function testBoltSetupWrongScheme(): void
    {
        $client = ClientBuilder::create()->addBoltConnection('bolt', 'neo4j://neo4j:test@neo4j-42:7687')->build();
        $tsx = $client->openTransaction();
        self::assertTrue(true);
        $tsx->rollback();
    }

    public function testHttpSetupWrongScheme(): void
    {
        $client = ClientBuilder::create()->addHttpConnection('http', 'neo4j://neo4j:test@neo4j-42:7474')->build();
        $this->expectException(NetworkException::class);
        $client->openTransaction();
    }

    public function testHttpWithDatabase(): void
    {
        $client = ClientBuilder::create()->addHttpConnection('http', 'http://neo4j:test@neo4j-42', HttpConfig::create()->withDatabase('abc'))->build();
        $this->expectException(Neo4jException::class);
        $client->openTransaction();
    }

    public function testBoltWithDatabase(): void
    {
        $client = ClientBuilder::create()->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j-42', BoltConfig::create()->withDatabase('abc'))->build();
        $this->expectException(Neo4jException::class);
        $client->openTransaction();
    }
}
