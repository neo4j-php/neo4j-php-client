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
use Laudis\Neo4j\Common\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

final class BoltDriverIntegrationTest extends TestCase
{
    private UriInterface $uri;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uri = $this->getBoltUri();
    }

    private function getBoltUri(): UriInterface
    {
        foreach (explode(',', (string) getenv('NEO4J_CONNECTIONS')) as $uri) {
            $psrUri = Uri::create($uri);
            if ($psrUri->getScheme() === 'bolt') {
                return $psrUri;
            }
        }

        return Uri::create('bolt://neo4j:test@neo4j');
    }

    /**
     * @throws Exception
     */
    public function testValidHostname(): void
    {
        $results = BoltDriver::create($this->uri->__toString())->createSession()->run(<<<'CYPHER'
RETURN 1 AS x
CYPHER);
        self::assertEquals(1, $results->first()->get('x'));
    }

    /**
     * @throws Exception
     */
    public function testValidUrl(): void
    {
        $ip = gethostbyname($this->uri->getHost());
        $results = BoltDriver::create($this->uri->withHost($ip)->__toString())->createSession()->run(<<<'CYPHER'
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
