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
use InvalidArgumentException;
use Laudis\Neo4j\ClientBuilder;
use PHPUnit\Framework\TestCase;

final class ClientBuilderTest extends TestCase
{
    public function testEmpty(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Client cannot be built with an empty connectionpool');
        ClientBuilder::create()->build();
    }

    public function testBadDefault(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Client cannot be built with a default connection "error" that is not in the connection pool');

        ClientBuilder::create()
            ->addHttpConnection('temp', 'http://neoj:test@neo4j')
            ->setDefaultConnection('error')
            ->build();
    }

    public function testBadHttpUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The provided url must have a parsed host, user, pass and scheme value');

        ClientBuilder::create()
            ->addHttpConnection('temp', 'neoj:test');
    }

    public function testBadBoltUrl(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The provided url must have a parsed host, user, pass and scheme value');

        ClientBuilder::create()
            ->addBoltConnection('temp', 'neoj:test');
    }

    public function testBoltSetupNoScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $client = ClientBuilder::create()->addBoltConnection('bolt', 'neo4j:test@neo4j:7687')->build();
        $client->openTransaction();
    }

    public function testBoltSetupWithScheme(): void
    {
        $client = ClientBuilder::create()->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j:7687')->build();
        $client->openTransaction();
        self::assertTrue(true);
    }

    public function testBoltSetupWithoutPort(): void
    {
        $client = ClientBuilder::create()->addBoltConnection('bolt', 'bolt://neo4j:test@neo4j')->build();
        $client->openTransaction();
        self::assertTrue(true);
    }

    public function testBoltSetupWithoutUserAndPass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $client = ClientBuilder::create()->addBoltConnection('bolt', 'bolt://@neo4j')->build();
        $client->openTransaction();
    }

    public function testBoltEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $client = ClientBuilder::create()->addBoltConnection('bolt', '')->build();
        $client->openTransaction();
    }

    public function testBoltSetupWrongScheme(): void
    {
        $client = ClientBuilder::create()->addBoltConnection('bolt', 'neo4j://neo4j:test@neo4j:7687')->build();
        $client->openTransaction();
        self::assertTrue(true);
    }

    public function testHttpSetupNoScheme(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $client = ClientBuilder::create()->addHttpConnection('http', 'test:neo4j@neo4j:7474')->build();
        $client->openTransaction();
    }

    public function testHttpSetupWithScheme(): void
    {
        $client = ClientBuilder::create()->addHttpConnection('http', 'http://neo4j:test@neo4j:7474')->build();
        $client->openTransaction();
        self::assertTrue(true);
    }

    public function testHttpSetupWrongScheme(): void
    {
        $client = ClientBuilder::create()->addHttpConnection('http', 'neo4j://neo4j:test@neo4j:7474')->build();
        $this->expectException(NetworkException::class);
        $client->openTransaction();
    }

    public function testHttpSetupWithoutPort(): void
    {
        $client = ClientBuilder::create()->addHttpConnection('http', 'http://neo4j:test@neo4j')->build();
        $client->openTransaction();
        self::assertTrue(true);
    }

    public function testHttpSetupWithoutUserAndPass(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $client = ClientBuilder::create()->addHttpConnection('http', 'http://@neo4j')->build();
        $client->openTransaction();
    }

    public function testHttpEmpty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $client = ClientBuilder::create()->addHttpConnection('http', '')->build();
        $client->openTransaction();
    }
}
