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

use Bolt\error\ConnectionTimeoutException;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\ConnectionPool;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Contracts\SemaphoreInterface;
use Laudis\Neo4j\Databags\ConnectionRequestData;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;
use Laudis\Neo4j\Enum\SslMode;
use Laudis\Neo4j\Exception\TimeoutException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\UriInterface;
use ReflectionClass;

class ConnectionPoolTest extends TestCase
{
    private MockObject&SemaphoreInterface $semaphore;
    private MockObject&BoltFactory $factory;
    private ConnectionRequestData $requestData;
    private MockObject&Neo4jLogger $logger;
    private SessionConfiguration $sessionConfig;
    private MockObject&UriInterface $uri;
    private MockObject&AuthenticateInterface $auth;

    protected function setUp(): void
    {
        $this->semaphore = $this->createMock(SemaphoreInterface::class);
        $this->factory = $this->createMock(BoltFactory::class);
        $this->sessionConfig = SessionConfiguration::default();
        $this->logger = $this->createMock(Neo4jLogger::class);
        $this->uri = $this->createMock(UriInterface::class);
        $this->auth = $this->createMock(AuthenticateInterface::class);

        $this->uri->method('getHost')->willReturn('localhost');

        $this->requestData = new ConnectionRequestData(
            'localhost',
            $this->uri,
            $this->auth,
            'test-user-agent',
            new SslConfiguration(SslMode::DISABLE(), false)
        );
    }

    public function testTimeoutExceptionIsThrown(): void
    {
        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessage('Connection timed out');

        $this->semaphore->method('wait')->willReturn((function () {
            yield 0.1;
        })());

        $this->factory->method('createConnection')
            ->willThrowException(new ConnectionTimeoutException('Connection timed out'));

        $pool = new ConnectionPool(
            $this->semaphore,
            $this->factory,
            $this->requestData,
            $this->logger,
            0.5,
            0.5,
            10
        );

        $generator = $pool->acquire($this->sessionConfig);

        while ($generator->valid()) {
            $generator->send(true);
        }

        $generator->getReturn();
    }

    public function testReuseConnectionIfPossibleReturnsReusableConnection(): void
    {
        $connection = $this->createMock(BoltConnection::class);
        $connection->method('getServerState')->willReturn('READY');
        $this->factory->method('canReuseConnection')->willReturn(true);
        $this->factory->method('reuseConnection')->willReturn($connection);

        $pool = $this->getMockBuilder(ConnectionPool::class)
            ->onlyMethods(['isConnectionExpired'])
            ->setConstructorArgs([
                $this->semaphore,
                $this->factory,
                $this->requestData,
                $this->logger,
                1.0,
                1.0,
                3600.0,
            ])
            ->getMock();
        $pool->expects($this->once())
            ->method('isConnectionExpired')
            ->with($connection)
            ->willReturn(false);
        $reflection = new ReflectionClass(ConnectionPool::class);
        $property = $reflection->getProperty('activeConnections');
        $property->setValue($pool, [$connection]);
        $method = $reflection->getMethod('reuseConnectionIfPossible');
        $result = $method->invoke($pool, $this->sessionConfig);
        $this->assertSame($connection, $result);
    }

    public function testReuseConnectionIfPossibleReturnsNullWhenNoReusableConnectionFound(): void
    {
        $connection = $this->createMock(BoltConnection::class);
        $connection->method('getServerState')->willReturn('READY');
        $this->factory->method('canReuseConnection')->willReturn(false);
        $pool = $this->getMockBuilder(ConnectionPool::class)
            ->onlyMethods(['isConnectionExpired'])
            ->setConstructorArgs([
                $this->semaphore,
                $this->factory,
                $this->requestData,
                $this->logger,
                1.0,
                1.0,
                3600.0,
            ])
            ->getMock();

        $pool->expects($this->never())->method('isConnectionExpired');
        $reflection = new ReflectionClass(ConnectionPool::class);
        $property = $reflection->getProperty('activeConnections');
        $property->setValue($pool, [$connection]);
        $method = $reflection->getMethod('reuseConnectionIfPossible');
        $result = $method->invoke($pool, $this->sessionConfig);
        $this->assertNull($result);
    }
}
