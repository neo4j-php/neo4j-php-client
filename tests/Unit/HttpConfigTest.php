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

use Laudis\Neo4j\Http\HttpConfig;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class HttpConfigTest extends TestCase
{
    public function testConstruct(): void
    {
        $injections = HttpConfig::create();
        self::assertEquals('neo4j', $injections->getDatabase());
        $injections = new HttpConfig('abc');
        self::assertEquals('abc', $injections->getDatabase());
    }

    public function testSystem(): void
    {
        self::assertEquals('system', HttpConfig::create()->withDatabase('system')->getDatabase());
    }

    public function testWithDatabase(): void
    {
        self::assertEquals('test', HttpConfig::create()->withDatabase('test')->getDatabase());
        self::assertEquals('test', HttpConfig::create()->withDatabase(static fn () => 'test')->getDatabase());
    }

    public function testWithClient(): void
    {
        $client = $this->getMockBuilder(ClientInterface::class)->getMock();
        self::assertSame($client, HttpConfig::create()->withClient($client)->getClient());
        self::assertSame($client, HttpConfig::create()->withClient(static fn () => $client)->getClient());
    }

    public function testWithRequestFactory(): void
    {
        $factory = $this->getMockBuilder(RequestFactoryInterface::class)->getMock();
        self::assertSame($factory, HttpConfig::create()->withRequestFactory($factory)->getRequestFactory());
        self::assertSame($factory, HttpConfig::create()
            ->withRequestFactory(static fn () => $factory)->getRequestFactory()
        );
    }

    public function testWithStreamFactory(): void
    {
        $factory = $this->getMockBuilder(StreamFactoryInterface::class)->getMock();
        self::assertSame($factory, HttpConfig::create()->withStreamFactory($factory)->getStreamFactory());
        self::assertSame($factory, HttpConfig::create()->withStreamFactory(static fn () => $factory)->getStreamFactory());
    }
}
