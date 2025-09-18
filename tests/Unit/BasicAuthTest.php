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

namespace Laudis\Neo4j\Tests\Unit;

use Laudis\Neo4j\Authentication\BasicAuth;
use Laudis\Neo4j\Common\Neo4jLogger;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

class BasicAuthTest extends TestCase
{
    private BasicAuth $auth;
    private string $username = 'neo4j';
    private string $password = 'test';

    protected function setUp(): void
    {
        $logger = $this->createMock(Neo4jLogger::class);
        $this->auth = new BasicAuth($this->username, $this->password, $logger);
    }

    public function testToString(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('localhost');
        $uri->method('getPort')->willReturn(7687);

        $result = $this->auth->toString($uri);

        $this->assertSame('Basic neo4j:######@localhost:7687', $result);
    }

    /**
     * @throws Exception
     * @throws \Exception
     */
    public function testEmptyCredentials(): void
    {
        $emptyAuth = new BasicAuth('', '', null);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('localhost');
        $uri->method('getPort')->willReturn(7687);

        $result = $emptyAuth->toString($uri);

        $this->assertSame('Basic :######@localhost:7687', $result);
    }
}
