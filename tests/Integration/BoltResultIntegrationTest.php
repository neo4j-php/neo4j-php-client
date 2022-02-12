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

use Bolt\Bolt;
use Bolt\connection\StreamSocket;
use Bolt\helpers\Auth;
use Bolt\protocol\V4;
use Dotenv\Dotenv;
use function explode;
use function is_string;
use Laudis\Neo4j\Bolt\BoltResult;
use Laudis\Neo4j\Common\Uri;
use PHPUnit\Framework\TestCase;

final class BoltResultIntegrationTest extends TestCase
{
    public function buildConnections(): array
    {
        $connections = $_ENV['NEO4J_CONNECTIONS'] ?? false;
        if (!is_string($connections)) {
            Dotenv::createImmutable(__DIR__.'/../../')->load();
            /** @var string|mixed $connections */
            $connections = $_ENV['NEO4J_CONNECTIONS'] ?? false;
            if (!is_string($connections)) {
                return ['bolt://neo4j:test@neo4j'];
            }
        }

        $tbr = [];
        foreach (explode(',', $connections) as $connection) {
            $tbr[] = [$connection];
        }

        return $tbr;
    }

    /**
     * @dataProvider  buildConnections
     */
    public function testIterationLong(string $connection): void
    {
        $uri = Uri::create($connection);
        $socket = new StreamSocket($uri->getHost(), $uri->getPort() ?? 7687);
        $socket->connect();
        $protocol = (new Bolt($socket))->build();
        if (!$protocol instanceof V4) {
            self::markTestSkipped('Can only test bolt result on v4');
        }
        $user = explode(':', $uri->getUserInfo());
        if (count($user) >= 2) {
            $protocol->hello(Auth::basic($user[0], $user[1]));
        } else {
            $protocol->hello(Auth::none());
        }
        $protocol->run('UNWIND range(1, 100000) AS i RETURN i');
        $i = 0;
        $result = new BoltResult($protocol, 1000);
        foreach ($result as $i => $x) {
            self::assertEquals($i + 1, $x[0] ?? 0);
        }

        self::assertEquals(100000, $i + 1);
        self::assertIsArray($result->consume());
    }
}
