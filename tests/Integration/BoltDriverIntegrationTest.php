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

use Bolt\error\ConnectException;
use Exception;
use Laudis\Neo4j\Basic\Driver;
use Laudis\Neo4j\Bolt\BoltDriver;
use Laudis\Neo4j\Tests\EnvironmentAwareIntegrationTest;
use Throwable;

final class BoltDriverIntegrationTest extends EnvironmentAwareIntegrationTest
{
    /**
     * @throws Exception
     *
     * @psalm-suppress MixedMethodCall
     */
    public function testValidHostname(): void
    {
        if (!str_contains($this->getUri()->getScheme(), 'bolt')) {
            $this->markTestSkipped('This test only works with Bolt drivers.');
        }

        $results = BoltDriver::create($this->getUri())
            ->createSession()
            ->run('RETURN 1 AS x');

        self::assertEquals(1, $results->first()->get('x'));
    }

    /**
     * @throws Exception
     *
     * @psalm-suppress MixedMethodCall
     */
    public function testValidUrl(): void
    {
        if (!str_contains($this->getUri()->getScheme(), 'bolt')) {
            $this->markTestSkipped('This test only works with Bolt drivers.');
        }

        $ip = gethostbyname($this->getUri()->getHost());
        try {
            $results = BoltDriver::create($this->getUri()->withHost($ip)->__toString())
                ->createSession()
                ->run('RETURN 1 AS x');
        } catch (Throwable $e) {
            $this->markTestSkipped($e->getMessage());
        }

        self::assertEquals(1, $results->first()->get('x'));
    }

    /**
     * @throws Exception
     */
    public function testInvalidIp(): void
    {
        $driver = BoltDriver::create('bolt://neo4j:test@999.999.999.999');
        $this->expectException(ConnectException::class);
        $driver->createSession()->run('RETURN 1');
    }

    /**
     * @throws Exception
     */
    public function testInvalidSocket(): void
    {
        $driver = BoltDriver::create('bolt://neo4j:test@999.999.999.999');
        $this->expectException(ConnectException::class);
        $driver->createSession()->run('RETURN 1');
    }

    public function testBookmarkUpdates(): void
    {
        $session = Driver::create($this->getUri(['bolt', 'neo4j'])->__toString())->createSession();
        $bookmark = $session->getLastBookmark();
        $this->assertEquals([], $bookmark->values());
        $this->assertTrue($bookmark->isEmpty());
        $previousBookmark = $bookmark;

        $result = $session->run('MATCH (x) RETURN x');
        $result->preload();

        $bookmark = $session->getLastBookmark();
        $this->assertFalse($bookmark->isEmpty());
        $this->assertNotEquals($previousBookmark->values(), $bookmark->values());
        $previousBookmark = $bookmark;

        $result = $session->run('CREATE (x:Node)');
        $result->preload();

        $bookmark = $session->getLastBookmark();
        $this->assertFalse($bookmark->isEmpty());
        $this->assertNotEquals($previousBookmark->values(), $bookmark->values());
    }
}
