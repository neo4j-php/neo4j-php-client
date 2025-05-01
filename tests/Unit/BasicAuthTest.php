<?php

declare(strict_types=1);


namespace Laudis\Neo4j\Tests\Unit;

use Bolt\enum\Message;
use Bolt\enum\Signature;
use Bolt\protocol\Response;
use Bolt\protocol\V5;
use Laudis\Neo4j\Authentication\BasicAuth;
use Laudis\Neo4j\Bolt\BoltMessageFactory;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Common\ResponseHelper;
use Laudis\Neo4j\Exception\Neo4jException;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use stdClass;

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

    public function testAuthenticateBoltFailure(): void
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
