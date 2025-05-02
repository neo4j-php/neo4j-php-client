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
use Laudis\Neo4j\Authentication\KerberosAuth;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Exception\Neo4jException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class KerberosAuthTest extends TestCase
{
    private KerberosAuth $auth;

    protected function setUp(): void
    {
        $logger = $this->createMock(Neo4jLogger::class);
        $this->auth = new KerberosAuth('test-token', $logger);
    }

    public function testAuthenticateHttpSuccess(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->exactly(2))
            ->method('withHeader')
            ->willReturnSelf();

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('localhost');
        $uri->method('getPort')->willReturn(7687);

        $auth = new KerberosAuth('test-token', null);
        $result = $auth->authenticateHttp($request, $uri, 'neo4j-client/1.0');

        $this->assertSame($request, $result);
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

    public function testAuthenticateBoltFailureV4(): void
    {
        $this->expectException(Neo4jException::class);

        $protocol = $this->createMock(V4_4::class);
        $response = new Response(
            Message::HELLO,
            Signature::FAILURE,
            ['code' => 'Neo.ClientError.Security.Unauthorized', 'message' => 'Invalid credentials']
        );

        $protocol->method('getResponse')->willReturn($response);

        $this->auth->authenticateBolt($protocol, 'neo4j-client/1.0');
    }

    public function testToString(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('localhost');
        $uri->method('getPort')->willReturn(7687);

        $result = $this->auth->toString($uri);

        $this->assertSame('Kerberos test-token@localhost:7687', $result);
    }

    public function testEmptyCredentials(): void
    {
        $emptyAuth = new KerberosAuth('', null);

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getHost')->willReturn('localhost');
        $uri->method('getPort')->willReturn(7687);

        $result = $emptyAuth->toString($uri);

        $this->assertSame('Kerberos @localhost:7687', $result);
    }
}
