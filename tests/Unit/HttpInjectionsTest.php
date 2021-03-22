<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Tests\Unit;

use Laudis\Neo4j\Network\Http\HttpInjections;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class HttpInjectionsTest extends TestCase
{
    public function testConstruct(): void
    {
        $injections = HttpInjections::create();
        self::assertEquals('neo4j', $injections->database());
        $injections = new HttpInjections('abc');
        self::assertEquals('abc', $injections->database());
    }

    public function testSystem(): void
    {
        self::assertEquals('system', HttpInjections::create()->withDatabase('system')->database());
    }

    public function testWithDatabase(): void
    {
        self::assertEquals('test', HttpInjections::create()->withDatabase('test')->database());
        self::assertEquals('test', HttpInjections::create()->withDatabase(static fn () => 'test')->database());
    }

    public function testWithClient(): void
    {
        $client = $this->getMockBuilder(ClientInterface::class)->getMock();
        self::assertSame($client, HttpInjections::create()->withClient($client)->client());
        self::assertSame($client, HttpInjections::create()->withClient(static fn () => $client)->client());
    }

    public function testWithRequestFactory(): void
    {
        $factory = $this->getMockBuilder(RequestFactoryInterface::class)->getMock();
        self::assertSame($factory, HttpInjections::create()->withRequestFactory($factory)->requestFactory());
        self::assertSame($factory, HttpInjections::create()
            ->withRequestFactory(static fn () => $factory)->requestFactory()
        );
    }

    public function testWithStreamFactory(): void
    {
        $factory = $this->getMockBuilder(StreamFactoryInterface::class)->getMock();
        self::assertSame($factory, HttpInjections::create()->withStreamFactory($factory)->streamFactory());
        self::assertSame($factory, HttpInjections::create()->withStreamFactory(static fn () => $factory)->streamFactory());
    }
}
