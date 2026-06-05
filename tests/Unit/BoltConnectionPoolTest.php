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

use Bolt\protocol\V5;
use Generator;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\ConnectionPool;
use Laudis\Neo4j\BoltFactory;
use Laudis\Neo4j\Common\GeneratorHelper;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Contracts\ConnectionInterface;
use Laudis\Neo4j\Contracts\SemaphoreInterface;
use Laudis\Neo4j\Databags\ConnectionRequestData;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\SslConfiguration;

use function microtime;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

use function sleep;

class BoltConnectionPoolTest extends TestCase
{
    private ConnectionPool $pool;
    /** @var SemaphoreInterface&MockObject */
    private SemaphoreInterface $semaphore;
    /** @var BoltFactory&MockObject */
    private BoltFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupPool((function (): Generator {
            yield 1.0;

            return true;
        })());
    }

    public function testSimpleAcquire(): void
    {
        $generator = $this->pool->acquire(SessionConfiguration::default());

        $connection = GeneratorHelper::getReturnFromGenerator($generator);

        self::assertInstanceOf(ConnectionInterface::class, $connection);
    }

    public function testTimingAcquire(): void
    {
        $generator = $this->pool->acquire(SessionConfiguration::default());
        $time = microtime(true);

        sleep(1);

        $result = $generator->current();
        $delta = microtime(true) - $time;

        $generator->next();
        $generator->getReturn();

        self::assertEqualsWithDelta($delta, $result, 0.05);
        self::assertEqualsWithDelta(1.0, $result, 0.05);
    }

    public function testMultipleWaits(): void
    {
        $this->setupPool((function (): Generator {
            yield 1.0;
            yield 2.0;
            yield 3.0;

            return true;
        })());

        $generator = $this->pool->acquire(SessionConfiguration::default());
        $count = 0;
        while ($generator->valid()) {
            ++$count;
            $generator->next();
        }

        static::assertEquals(3, $count);
    }

    public function testRelease(): void
    {
        $this->semaphore->expects(self::once())->method('post');

        $this->pool->release($this->createMock(ConnectionInterface::class));
    }

    public function testReleaseKeepsConnectionInPool(): void
    {
        $connection = $this->pool->acquire(SessionConfiguration::default());
        $connection->next();
        $connection = $connection->getReturn();

        static::assertInstanceOf(ConnectionInterface::class, $connection);

        $this->pool->release($connection);

        $reflection = new ReflectionClass(ConnectionPool::class);
        $property = $reflection->getProperty('activeConnections');
        /** @var list<ConnectionInterface> $activeConnections */
        $activeConnections = $property->getValue($this->pool);

        static::assertContains($connection, $activeConnections);
    }

    private function setupPool(Generator $semaphoreGenerator): void
    {
        $this->semaphore = $this->createMock(SemaphoreInterface::class);
        $this->semaphore->method('wait')
                        ->willReturn($semaphoreGenerator);

        $this->factory = $this->createMock(BoltFactory::class);
        $boltConnection = $this->createMock(BoltConnection::class);
        $boltConnection->method('protocol')->willReturn($this->createMock(V5::class));
        $this->factory->method('createConnection')
                      ->willReturn($boltConnection);
        $this->factory->method('reuseConnection')
            ->willReturnCallback(fn (MockObject $x): MockObject => $x);

        $this->pool = new ConnectionPool(
            $this->semaphore,
            $this->factory,
            new ConnectionRequestData(
                '',
                Uri::create(''),
                Authenticate::disabled(),
                '',
                SslConfiguration::default()
            ),
            null,
            10.0
        );
    }
}
