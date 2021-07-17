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

use Bolt\error\ConnectException;
use Exception;
use Laudis\Neo4j\Bolt\BoltDriver;
use PHPUnit\Framework\TestCase;

final class BoltDriverIntegrationTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testValidHostname(): void
    {
        $results = BoltDriver::create('bolt://neo4j:test@neo4j')->createSession()->run(<<<'CYPHER'
RETURN 1 AS x
CYPHER);
        self::assertEquals(1, $results->first()->get('x'));
    }

    /**
     * @throws Exception
     */
    public function testValidUrl(): void
    {
        $ip = gethostbyname('neo4j');
        $results = BoltDriver::create('bolt://neo4j:test@'.$ip)->createSession()->run(<<<'CYPHER'
RETURN 1 AS x
CYPHER);
        self::assertEquals(1, $results->first()->get('x'));
    }

    /**
     * @throws Exception
     */
    public function testInvalidIp(): void
    {
        $driver = BoltDriver::create('bolt://neo4j:test@127.0.0.0');
        $this->expectException(ConnectException::class);
        $driver->createSession()->run('RETURN 1');
    }

    /**
     * @throws Exception
     */
    public function testInvalidSocket(): void
    {
        $driver = BoltDriver::create('bolt://neo4j:test@127.0.0.0');
        $this->expectException(ConnectException::class);
        $driver->createSession()->run('RETURN 1');
    }
}
