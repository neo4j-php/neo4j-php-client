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
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
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
    public function testAuthenticateBoltSuccess(): void
    {
        $userAgent = 'neo4j-client/1.0';

        $mockProtocol = $this->createMock(V5::class);
        $mockProtocol->method('hello');
        $mockProtocol->method('getResponse')->willReturn(new Response(
            Message::HELLO,
            Signature::SUCCESS,
            ['server' => 'neo4j-server', 'connection_id' => '12345', 'hints' => []]
        ));

        $mockConnection = $this->createMock(BoltConnection::class);
        $mockConnection->method('protocol')->willReturn($mockProtocol);

        $result = $this->auth->authenticateBolt($mockConnection, $userAgent);
        $this->assertArrayHasKey('server', $result);
        $this->assertSame('neo4j-server', $result['server']);
        $this->assertSame('12345', $result['connection_id']);
    }

    public function testAuthenticateBoltFailure(): void
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
