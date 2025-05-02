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

use Bolt\enum\Message;
use Bolt\enum\Signature;
use Bolt\protocol\Response;
use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Laudis\Neo4j\Authentication\NoAuth;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Exception\Neo4jException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class NoAuthTest extends TestCase
{
    private NoAuth $auth;

    protected function setUp(): void
    {
        $logger = $this->createMock(Neo4jLogger::class);
        $this->auth = new NoAuth($logger);
    }

    public function testAuthenticateHttpSuccess(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('withHeader')
            ->with('User-Agent', 'neo4j-client/1.0')
            ->willReturnSelf();

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('localhost');
        $uri->method('getPort')->willReturn(7687);

        $result = $this->auth->authenticateHttp($request, $uri, 'neo4j-client/1.0');
        $this->assertSame($request, $result);
    }

    public function testAuthenticateBoltSuccessV5(): void
    {
        $userAgent = 'neo4j-client/1.0';

        $protocol = $this->createMock(V5::class);

        $response = new Response(
            Message::HELLO,
            Signature::SUCCESS,
            ['server' => 'neo4j-server', 'connection_id' => '12345', 'hints' => []]
        );

        $protocol->expects($this->once())
            ->method('getResponse')
            ->willReturn($response);

        $result = $this->auth->authenticateBolt($protocol, $userAgent);
        $this->assertArrayHasKey('server', $result);
        $this->assertSame('neo4j-server', $result['server']);
        $this->assertSame('12345', $result['connection_id']);
    }

    public function testAuthenticateBoltFailureV5(): void
    {
        $this->expectException(Neo4jException::class);

        $protocol = $this->createMock(V5::class);
        $response = new Response(
            Message::HELLO,
            Signature::FAILURE,
            ['code' => 'Neo.ClientError.Security.Unauthorized', 'message' => 'Invalid credentials']
        );

        $protocol->method('getResponse')->willReturn($response);

        $this->auth->authenticateBolt($protocol, 'neo4j-client/1.0');
    }

    public function testAuthenticateBoltSuccessV4(): void
    {
        $userAgent = 'neo4j-client/1.0';

        $protocol = $this->createMock(V4_4::class);

        $response = new Response(
            Message::HELLO,
            Signature::SUCCESS,
            ['server' => 'neo4j-server', 'connection_id' => '12345', 'hints' => []]
        );

        $protocol->expects($this->once())
            ->method('getResponse')
            ->willReturn($response);

        $result = $this->auth->authenticateBolt($protocol, $userAgent);
        $this->assertArrayHasKey('server', $result);
        $this->assertSame('neo4j-server', $result['server']);
        $this->assertSame('12345', $result['connection_id']);
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
