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

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Basic\Driver;
use Laudis\Neo4j\Bolt\BoltDriver;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Types\CypherMap;

final class BasicDriverTest extends EnvironmentAwareIntegrationTest
{
    public function testFullWalk(): void
    {
        $driver = Driver::create($this->getUri());

        $session = $driver->createSession();

        $session->run('MERGE (x:X {id: 0})');

        $id = 1;
        $result = $session->run('MATCH (x:X {id: 0}) RETURN x');
        $result->each(static function (CypherMap $map) use (&$id) {
            $id = $map->getAsNode('x')->getProperties()->getAsInt('id');
        });

        self::assertEquals(0, $id);
    }

    public function testInvalidAuth(): void
    {
        $uri = $this->getUri()->withUserInfo('');

        $this->expectException(Neo4jException::class);
        BoltDriver::create($uri, null, Authenticate::basic('x', 'y'))
            ->createSession()
            ->run('RETURN 1 AS one');
    }
}
