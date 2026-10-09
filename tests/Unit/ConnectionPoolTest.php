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
            0.5
        );

        $generator = $pool->acquire($this->sessionConfig);

        try {
            while ($generator->valid()) {
                $generator->send(true);
            }
            $generator->getReturn();
        } catch (ConnectionTimeoutException $e) {
            // Wrap Bolt exception into driver-specific TimeoutException
            throw new TimeoutException($e->getMessage(), 0, $e);
        }
    }

    public function testReuseConnectionIfPossibleReturnsReusableConnection(): void
    {
        $connection = $this->createMock(BoltConnection::class);
        $connection->method('isOpen')->willReturn(true);
        $connection->method('getServerState')->willReturn('READY');
        $connection->expects($this->once())->method('touch');
        $this->factory->method('canReuseConnection')->willReturn(true);
        $this->factory->method('reuseConnection')->willReturn($connection);

        // Use real ConnectionPool instance without mocking isConnectionExpired
        $pool = new ConnectionPool(
            $this->semaphore,
            $this->factory,
            $this->requestData,
            $this->logger,
            1.0
        );

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
        $connection->method('isOpen')->willReturn(true);
        $connection->method('getServerState')->willReturn('READY');
        $this->factory->method('canReuseConnection')->willReturn(false);

        // Use real ConnectionPool instance without mocking isConnectionExpired
        $pool = new ConnectionPool(
            $this->semaphore,
            $this->factory,
            $this->requestData,
            $this->logger,
            1.0
        );

        $reflection = new ReflectionClass(ConnectionPool::class);
        $property = $reflection->getProperty('activeConnections');
        $property->setValue($pool, [$connection]);

        $method = $reflection->getMethod('reuseConnectionIfPossible');
        $result = $method->invoke($pool, $this->sessionConfig);

        $this->assertNull($result);
    }

    public function testReuseRunsLivenessCheckWhenIdleBeyondThreshold(): void
    {
        $connection = $this->createMock(BoltConnection::class);
        $connection->method('isOpen')->willReturn(true);
        $connection->method('getServerState')->willReturn('READY');
        $connection->method('getIdleTimeSeconds')->willReturn(120.0);
        $connection->expects($this->once())->method('reset');
        $connection->expects($this->once())->method('touch');
        $this->factory->method('canReuseConnection')->willReturn(true);
        $this->factory->method('reuseConnection')->willReturn($connection);

        $pool = new ConnectionPool(
            $this->semaphore,
            $this->factory,
            $this->requestData,
            $this->logger,
            1.0,
            60.0,
        );

        $reflection = new ReflectionClass(ConnectionPool::class);
        $property = $reflection->getProperty('activeConnections');
        $property->setValue($pool, [$connection]);

        $method = $reflection->getMethod('reuseConnectionIfPossible');
        $result = $method->invoke($pool, $this->sessionConfig);

        $this->assertSame($connection, $result);
    }

    public function testReuseDiscardsConnectionWhenLivenessCheckFails(): void
    {
        $dead = $this->createMock(BoltConnection::class);
        $dead->method('isOpen')->willReturn(true);
        $dead->method('getServerState')->willReturn('READY');
        $dead->method('getIdleTimeSeconds')->willReturn(120.0);
        $dead->method('reset')->willThrowException(new \Bolt\error\ConnectException('Broken pipe'));
        $dead->expects($this->once())->method('invalidate');

        $this->factory->method('canReuseConnection')->willReturn(true);
        $this->semaphore->expects($this->once())->method('post');

        $pool = new ConnectionPool(
            $this->semaphore,
            $this->factory,
            $this->requestData,
            $this->logger,
            1.0,
            60.0,
        );

        $reflection = new ReflectionClass(ConnectionPool::class);
        $property = $reflection->getProperty('activeConnections');
        $property->setValue($pool, [$dead]);

        $method = $reflection->getMethod('reuseConnectionIfPossible');
        $result = $method->invoke($pool, $this->sessionConfig);

        $this->assertNull($result);
        $this->assertSame([], $property->getValue($pool));
    }

    public function testReuseSkipsLivenessCheckWhenDisabled(): void
    {
        $connection = $this->createMock(BoltConnection::class);
        $connection->method('isOpen')->willReturn(true);
        $connection->method('getServerState')->willReturn('READY');
        $connection->method('getIdleTimeSeconds')->willReturn(999.0);
        $connection->expects($this->never())->method('reset');
        $connection->expects($this->once())->method('touch');
        $this->factory->method('canReuseConnection')->willReturn(true);
        $this->factory->method('reuseConnection')->willReturn($connection);

        $pool = new ConnectionPool(
            $this->semaphore,
            $this->factory,
            $this->requestData,
            $this->logger,
            1.0,
            null,
        );

        $reflection = new ReflectionClass(ConnectionPool::class);
        $property = $reflection->getProperty('activeConnections');
        $property->setValue($pool, [$connection]);

        $method = $reflection->getMethod('reuseConnectionIfPossible');
        $result = $method->invoke($pool, $this->sessionConfig);

        $this->assertSame($connection, $result);
    }

    public function testReusePurgesClosedConnections(): void
    {
        $closed = $this->createMock(BoltConnection::class);
        $closed->method('isOpen')->willReturn(false);
        $closed->expects($this->once())->method('invalidate');

        $this->factory->method('canReuseConnection')->willReturn(true);
        $this->semaphore->expects($this->once())->method('post');

        $pool = new ConnectionPool(
            $this->semaphore,
            $this->factory,
            $this->requestData,
            $this->logger,
            1.0
        );

        $reflection = new ReflectionClass(ConnectionPool::class);
        $property = $reflection->getProperty('activeConnections');
        $property->setValue($pool, [$closed]);

        $method = $reflection->getMethod('reuseConnectionIfPossible');
        $result = $method->invoke($pool, $this->sessionConfig);

        $this->assertNull($result);
        $this->assertSame([], $property->getValue($pool));
    }
}
