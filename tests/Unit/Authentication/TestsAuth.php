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

namespace Laudis\Neo4j\Tests\Unit\Authentication;

use Bolt\protocol\Response;
use Bolt\protocol\V4_4;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use Nyholm\Psr7\Request;
use PHPUnit\Framework\TestCase;

/**
 * @mixin TestCase
 */
trait TestsAuth
{
    /**
     * @dataProvider provideHttp
     */
    public function testAuthenticateHttp(string $authHeader, string $userAgent, AuthenticateInterface $instance): void
    {
        $request = new Request('GET', '');

        $result = $instance->authenticateHttp($request, $userAgent);

        $this->assertEquals($authHeader, $result->getHeaderLine('Authorization'));
        $this->assertEquals($userAgent, $result->getHeaderLine('User-Agent'));
    }

    /**
     * @dataProvider provideBolt
     */
    public function testAuthenticateBolt(
        string $userAgent,
        AuthenticateInterface $instance,
        array $helloMessage,
        int $responseSignature
    ): void {
        $bolt = $this->getMockBuilder(V4_4::class)
            ->disableOriginalConstructor()
            ->getMock();

        $response = $this->getMockBuilder(Response::class)
            ->disableOriginalConstructor()
            ->getMock();

        $response->method('getSignature')
            ->willReturn($responseSignature);

        $contents = ['server' => 'a', 'connection_id' => 'b', 'hints' => []];
        if ($responseSignature === Response::SIGNATURE_FAILURE) {
            $contents = ['code' => 'a.b.c.d', 'message' => 'hello'];
        }

        $response->method('getContent')
            ->willReturn($contents);

        $bolt->expects($this->once())
            ->method('hello')
            ->with($helloMessage)
            ->willReturn($response);

        if ($responseSignature === Response::SIGNATURE_FAILURE) {
            $this->expectException(Neo4jException::class);

            $exception = new Neo4jException([Neo4jError::fromMessageAndCode('a.b.c.d', 'hello')]);
            $this->expectExceptionMessage($exception->getMessage());
        }

        $response = $instance->authenticate($bolt, $userAgent);

        $this->assertEquals($contents, $response);
    }

    /**
     * @dataProvider provideToString
     */
    public function testToString(string $expected, AuthenticateInterface $instance): void
    {
        $this->assertEquals($expected, (string) $instance);
    }

    /**
     * @return list<array{0: string, 1: string, 2: AuthenticateInterface}>
     */
    abstract public static function provideHttp(): array;

    /**
     * @return list<array{0: string, 1: AuthenticateInterface, 2: array, 3: int}>
     */
    abstract public static function provideBolt(): array;

    /**
     * @return list<array{0: string, 1: AuthenticateInterface}>
     */
    abstract public static function provideToString(): array;
}
