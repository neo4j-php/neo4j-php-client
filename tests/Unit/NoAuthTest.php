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
use Bolt\protocol\V5_1;
use Laudis\Neo4j\Authentication\NoAuth;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Exception\Neo4jException;
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
    public function testAuthenticateBoltSuccessV5(): void
    {
        $userAgent = 'neo4j-client/1.0';

        $mockProtocol = $this->createMock(V5_1::class);
        $mockProtocol->method('hello');
        $mockProtocol->method('logon');
        $mockProtocol->method('getResponse')->willReturn(new Response(
            Message::HELLO,
            Signature::SUCCESS,
            ['server' => 'neo4j-server', 'connection_id' => '12345', 'hints' => []]
        ));

        $mockConnection = $this->createMock(BoltConnection::class);
        $mockConnection->method('protocol')->willReturn($mockProtocol);
        $mockConnection->method('getProtocol')->willReturn(ConnectionProtocol::BOLT_V5_1());

        $result = $this->auth->authenticateBolt($mockConnection, $userAgent);
        $this->assertArrayHasKey('server', $result);
        $this->assertSame('neo4j-server', $result['server']);
        $this->assertSame('12345', $result['connection_id']);
    }

    public function testAuthenticateBoltFailureV5(): void
    {
        $this->expectException(Neo4jException::class);

        $mockProtocol = $this->createMock(V5_1::class);
        $mockProtocol->method('hello');
        $mockProtocol->method('logon');
        $mockProtocol->method('getResponse')->willReturn(new Response(
            Message::HELLO,
            Signature::FAILURE,
            ['code' => 'Neo.ClientError.Security.Unauthorized', 'message' => 'Invalid credentials']
        ));

        $mockConnection = $this->createMock(BoltConnection::class);
        $mockConnection->method('protocol')->willReturn($mockProtocol);
        $mockConnection->method('getProtocol')->willReturn(ConnectionProtocol::BOLT_V5_1());

        $error = Neo4jError::fromMessageAndCode('Neo.ClientError.Security.Unauthorized', 'Invalid credentials');
        $exception = new Neo4jException([$error]);
        $mockConnection->method('assertNoFailure')->will($this->throwException($exception));

        $this->auth->authenticateBolt($mockConnection, 'neo4j-client/1.0');
    }

    public function testAuthenticateBoltSuccessV4(): void
    {
        $userAgent = 'neo4j-client/1.0';

        $mockProtocol = $this->createMock(V4_4::class);
        $mockProtocol->method('hello');
        $mockProtocol->method('getResponse')->willReturn(new Response(
            Message::HELLO,
            Signature::SUCCESS,
            ['server' => 'neo4j-server', 'connection_id' => '12345', 'hints' => []]
        ));

        $mockConnection = $this->createMock(BoltConnection::class);
        $mockConnection->method('protocol')->willReturn($mockProtocol);
        $mockConnection->method('getProtocol')->willReturn(ConnectionProtocol::BOLT_V44());

        $result = $this->auth->authenticateBolt($mockConnection, $userAgent);
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
