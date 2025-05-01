<?php

declare(strict_types=1);


namespace Laudis\Neo4j\Tests\Unit;

use Laudis\Neo4j\Authentication\OpenIDConnectAuth;
use Laudis\Neo4j\Common\Neo4jLogger;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;

class OpenIDConnectionAuthTest extends TestCase
{
    private OpenIDConnectAuth $auth;

    protected function setUp(): void
    {
        $this->auth = new OpenIDConnectAuth('test-token', $this->createMock(Neo4jLogger::class));
    }

    public function testAuthenticateHttpSuccess(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $uri = $this->createMock(UriInterface::class);

        $request->expects($this->exactly(2))
            ->method('withHeader')
            ->willReturnSelf();

        $uri->method('getHost')->willReturn('localhost');
        $uri->method('getPort')->willReturn(7687);

        $result = $this->auth->authenticateHttp($request, $uri, 'neo4j-client/1.0');

        $this->assertSame($request, $result);
    }

    public function testAuthenticateHttpAddsAuthorizationHeader(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $uri = $this->createMock(UriInterface::class);

        $sequence = 0;

        $request->expects($this->exactly(2))
            ->method('withHeader')
            ->willReturnCallback(function (string $header, string $value) use (&$sequence, $request) {
                if ($sequence === 0) {
                    TestCase::assertSame('Authorization', $header);
                    TestCase::assertSame('Bearer test-token', $value);
                } elseif ($sequence === 1) {
                    TestCase::assertSame('User-Agent', $header);
                    TestCase::assertSame('neo4j-client/1.0', $value);
                } else {
                    TestCase::fail('Unexpected header call');
                }

                ++$sequence;

                return $request;
            });

        $uri->method('getHost')->willReturn('localhost');
        $uri->method('getPort')->willReturn(7687);

        $result = $this->auth->authenticateHttp($request, $uri, 'neo4j-client/1.0');

        $this->assertSame($request, $result);
    }

    public function testAuthenticateHttpWithDifferentUri(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $uri = $this->createMock(UriInterface::class);

        $request->expects($this->exactly(2))
            ->method('withHeader')
            ->willReturnSelf();

        $uri->method('getHost')->willReturn('my-neo4j-host');
        $uri->method('getPort')->willReturn(7474);

        $result = $this->auth->authenticateHttp($request, $uri, 'neo4j-client/2.0');

        $this->assertSame($request, $result);
    }

    public function testAuthenticateHttpWithHeaderReturnsNewInstance(): void
    {
        $initialRequest = $this->createMock(RequestInterface::class);
        $modifiedRequest = $this->createMock(RequestInterface::class);
        $finalRequest = $this->createMock(RequestInterface::class);
        $uri = $this->createMock(UriInterface::class);

        $initialRequest->expects($this->once())
            ->method('withHeader')
            ->with('Authorization', 'Bearer test-token')
            ->willReturn($modifiedRequest);

        $modifiedRequest->expects($this->once())
            ->method('withHeader')
            ->with('User-Agent', 'neo4j-client/1.0')
            ->willReturn($finalRequest);

        $uri->method('getHost')->willReturn('localhost');
        $uri->method('getPort')->willReturn(7687);

        $result = $this->auth->authenticateHttp($initialRequest, $uri, 'neo4j-client/1.0');

        $this->assertSame($finalRequest, $result);
    }
}
