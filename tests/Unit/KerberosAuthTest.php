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

use Laudis\Neo4j\Authentication\KerberosAuth;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;

class KerberosAuthTest extends TestCase
{
    private KerberosAuth $auth;

    protected function setUp(): void
    {
        $logger = $this->createMock(Neo4jLogger::class);
        $this->auth = new KerberosAuth('test-token', $logger);
    }

    public function testAuthenticateBoltFailureV5(): void
    {
        $this->expectException(Neo4jException::class);

        $mockProtocol = $this->createMock(V5::class);
        $mockProtocol->method('hello');
        $mockProtocol->method('getResponse')->willReturn(new Response(
            Message::HELLO,
            Signature::FAILURE,
            ['code' => 'Neo.ClientError.Security.Unauthorized', 'message' => 'Invalid credentials']
        ));

        $mockConnection = $this->createMock(BoltConnection::class);
        $mockConnection->method('protocol')->willReturn($mockProtocol);

        $error = Neo4jError::fromMessageAndCode('Neo.ClientError.Security.Unauthorized', 'Invalid credentials');
        $exception = new Neo4jException([$error]);
        $mockConnection->method('assertNoFailure')->will($this->throwException($exception));

        $this->auth->authenticateBolt($mockConnection, 'neo4j-client/1.0');
    }

    public function testAuthenticateBoltFailureV4(): void
    {
        $this->expectException(Neo4jException::class);

        $mockProtocol = $this->createMock(V4_4::class);
        $mockProtocol->method('hello');
        $mockProtocol->method('getResponse')->willReturn(new Response(
            Message::HELLO,
            Signature::FAILURE,
            ['code' => 'Neo.ClientError.Security.Unauthorized', 'message' => 'Invalid credentials']
        ));

        $mockConnection = $this->createMock(BoltConnection::class);
        $mockConnection->method('protocol')->willReturn($mockProtocol);

        $error = Neo4jError::fromMessageAndCode('Neo.ClientError.Security.Unauthorized', 'Invalid credentials');
        $exception = new Neo4jException([$error]);
        $mockConnection->method('assertNoFailure')->will($this->throwException($exception));

        $this->auth->authenticateBolt($mockConnection, 'neo4j-client/1.0');
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
