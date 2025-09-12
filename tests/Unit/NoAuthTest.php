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

use Laudis\Neo4j\Authentication\NoAuth;
use Laudis\Neo4j\Common\Neo4jLogger;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

class NoAuthTest extends TestCase
{
    private NoAuth $auth;

    protected function setUp(): void
    {
        $logger = $this->createMock(Neo4jLogger::class);
        $this->auth = new NoAuth($logger);
    }

    public function testToString(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('localhost');
        $uri->method('getPort')->willReturn(7687);

        $result = $this->auth->toString($uri);

        $this->assertSame('No Auth localhost:7687', $result);
    }

    public function testEmptyCredentials(): void
    {
        $emptyAuth = new NoAuth(null);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('localhost');
        $uri->method('getPort')->willReturn(7687);

        $result = $emptyAuth->toString($uri);

        $this->assertSame('No Auth localhost:7687', $result);
    }
}
