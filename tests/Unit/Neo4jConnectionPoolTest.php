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
use Bolt\protocol\V5_4;
use Generator;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\Session;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\Cache;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\AddressResolverInterface;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\SemaphoreInterface;
use Laudis\Neo4j\Databags\ConnectionRequestData;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Enum\SslMode;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Neo4j\Neo4jConnectionPool;
use Laudis\Neo4j\Neo4j\RoutingTable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class Neo4jConnectionPoolTest extends TestCase
{
    private Neo4jConnectionPool $pool;
    private MockObject&V5_4 $protocol;

    protected function setUp(): void
    {
        Cache::getInstance()->clear();

        $this->protocol = $this->createMock(V5_4::class);
        $this->protocol->method('getResponse')->willReturn(new Response(Message::ROUTE, Signature::SUCCESS, [
            'rt' => [
                'servers' => [
                    ['addresses' => ['localhost:7687'], 'role' => 'WRITE'],
                    ['addresses' => ['localhost:7687'], 'role' => 'READ'],
                    ['addresses' => ['localhost:7687'], 'role' => 'ROUTE'],
                ],
                'ttl' => 300,
                'db' => 'home-db',
            ],
        ]));

        $connection = $this->createMock(BoltConnection::class);
        $connection->method('isOpen')->willReturn(true);
        $connection->method('getServerState')->willReturn('READY');
        $connection->method('protocol')->willReturn($this->protocol);

        $factory = $this->createMock(BoltFactory::class);
        $factory->method('createConnection')->willReturn($connection);
        $factory->method('canReuseConnection')->willReturn(true);
        $factory->method('reuseConnection')->willReturnArgument(0);

        $semaphore = $this->createMock(SemaphoreInterface::class);
        $semaphore->method('wait')->willReturnCallback(static fn (): Generator => (static function () {
            yield from [];
        })());

        $resolver = $this->createMock(AddressResolverInterface::class);
        $resolver->method('getAddresses')->willReturnCallback(static fn (): Generator => (static function () {
            yield from [];
        })());

        $this->pool = new Neo4jConnectionPool(
            $semaphore,
            $factory,
            new ConnectionRequestData(
                'localhost',
                Uri::create('neo4j://localhost:7687'),
                $this->createMock(AuthenticateInterface::class),
                'test-user-agent',
                new SslConfiguration(SslMode::DISABLE(), false),
            ),
            Cache::getInstance(),
            $resolver,
            null,
            1.0,
        );
    }

    protected function tearDown(): void
    {
        $this->pool->close();
    }

    public function testHomeDatabaseRoutingTableIsCachedUnderResolvedDatabase(): void
    {
        $config = SessionConfiguration::default();

        $this->protocol->expects($this->once())->method('route')->willReturnSelf();

        $this->acquire($config);
        $this->acquire($config->withDatabase('home-db'));

        $table = $this->pool->getRoutingTable($config);
        $this->assertInstanceOf(RoutingTable::class, $table);
        $this->assertSame('home-db', $table->getDatabase());
    }

    public function testSessionResolvesHomeDatabaseOncePerSession(): void
    {
        $this->protocol->expects($this->exactly(2))->method('route')->willReturnSelf();

        $session = new Session(SessionConfiguration::default(), $this->pool, SummarizedResultFormatter::create());
        $session->beginTransaction();
        $session->beginTransaction();

        $other = new Session(SessionConfiguration::default(), $this->pool, SummarizedResultFormatter::create());
        $other->beginTransaction();
    }

    public function testRoutingTableIsCachedForSessionWithDatabase(): void
    {
        $config = SessionConfiguration::default()->withDatabase('home-db');

        $this->protocol->expects($this->once())->method('route')->willReturnSelf();

        $this->acquire($config);
        $this->acquire($config);
    }

    private function acquire(SessionConfiguration $config): void
    {
        $generator = $this->pool->acquire($config);
        while ($generator->valid()) {
            $generator->send(true);
        }
        $this->assertInstanceOf(BoltConnection::class, $generator->getReturn());
    }
}
