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

use Laudis\Neo4j\Client;
use Laudis\Neo4j\Common\DriverSetupManager;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\SessionInterface;
use Laudis\Neo4j\Contracts\UnmanagedTransactionInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\ConnectionPoolException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ClientExceptionHandlingTest extends TestCase
{
    public function testClientRunStatementWithFailingDriver(): void
    {
        $driverSetupManager = $this->createMock(DriverSetupManager::class);
        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $driverSetupManager->method('getDriver')
            ->willThrowException(new RuntimeException(
                'Cannot connect to any server on alias: default with Uris: (\'neo4j://node1:7687\', \'neo4j://node2:7687\')'
            ));

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot connect to any server on alias: default');
        $client->run('RETURN 1 as n');
    }

    public function testClientWriteTransactionWithFailingDriver(): void
    {
        $driverSetupManager = $this->createMock(DriverSetupManager::class);
        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $driverSetupManager->method('getDriver')
            ->willThrowException(new RuntimeException(
                'Cannot connect to any server on alias: default with Uris: (\'neo4j://node1:7687\', \'neo4j://node2:7687\')'
            ));

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot connect to any server');

        $client->writeTransaction(function () {
            return 'test';
        });
    }

    public function testClientReadTransactionWithFailingDriver(): void
    {
        $driverSetupManager = $this->createMock(DriverSetupManager::class);
        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $driverSetupManager->method('getDriver')
            ->willThrowException(new RuntimeException(
                'Cannot connect to any server on alias: default with Uris: (\'neo4j://node1:7687\', \'neo4j://node2:7687\')'
            ));

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot connect to any server');

        $client->readTransaction(function () {
            return 'test';
        });
    }

    public function testClientBeginTransactionWithFailingDriver(): void
    {
        $driverSetupManager = $this->createMock(DriverSetupManager::class);
        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $driverSetupManager->method('getDriver')
            ->willThrowException(new RuntimeException(
                'Cannot connect to any server on alias: default with Uris: (\'neo4j://node1:7687\', \'neo4j://node2:7687\')'
            ));

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot connect to any server');

        $client->beginTransaction();
    }

    public function testClientExceptionIncludesFailedAliasInfo(): void
    {
        $driverSetupManager = $this->createMock(DriverSetupManager::class);
        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $driverSetupManager->method('getDriver')
            ->willThrowException(new RuntimeException(
                'Cannot connect to any server on alias: secondary with Uris: (\'neo4j://node4:7687\', \'neo4j://node5:7687\')'
            ));

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot connect to any server on alias: secondary');

        $client->run('RETURN 1 as n', [], 'secondary');
    }

    public function testWriteTransactionRecoversAfterConnectionPoolExceptionByResettingDriver(): void
    {
        $failingSessionMock = $this->createMock(SessionInterface::class);
        $successfulSessionMock = $this->createMock(SessionInterface::class);
        $firstDriverMock = $this->createMock(DriverInterface::class);
        $secondDriverMock = $this->createMock(DriverInterface::class);
        $driverSetupManager = $this->createMock(DriverSetupManager::class);

        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $firstDriverMock->method('createSession')->willReturn($failingSessionMock);
        $failingSessionMock->method('writeTransaction')
            ->willThrowException(new ConnectionPoolException('Write leader unavailable'));

        $secondDriverMock->method('createSession')->willReturn($successfulSessionMock);
        $successfulSessionMock->method('writeTransaction')->willReturn('recovered');

        $driverSetupManager->method('getDefaultAlias')->willReturn('default');
        $driverSetupManager->expects($this->exactly(2))
            ->method('getDriver')
            ->with($sessionConfig, 'default')
            ->willReturnOnConsecutiveCalls($firstDriverMock, $secondDriverMock);
        $driverSetupManager->expects($this->once())->method('resetDriver')->with('default');

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->assertSame(
            'recovered',
            $client->writeTransaction(static fn () => 'unused')
        );
    }

    public function testReadTransactionRecoversAfterConnectionPoolExceptionByResettingDriver(): void
    {
        $failingSessionMock = $this->createMock(SessionInterface::class);
        $successfulSessionMock = $this->createMock(SessionInterface::class);
        $firstDriverMock = $this->createMock(DriverInterface::class);
        $secondDriverMock = $this->createMock(DriverInterface::class);
        $driverSetupManager = $this->createMock(DriverSetupManager::class);

        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $firstDriverMock->method('createSession')->willReturn($failingSessionMock);
        $failingSessionMock->method('readTransaction')
            ->willThrowException(new ConnectionPoolException('Read replica unavailable'));

        $secondDriverMock->method('createSession')->willReturn($successfulSessionMock);
        $successfulSessionMock->method('readTransaction')->willReturn(42);

        $driverSetupManager->method('getDefaultAlias')->willReturn('default');
        $driverSetupManager->expects($this->exactly(2))
            ->method('getDriver')
            ->with($sessionConfig, 'default')
            ->willReturnOnConsecutiveCalls($firstDriverMock, $secondDriverMock);
        $driverSetupManager->expects($this->once())->method('resetDriver')->with('default');

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->assertSame(
            42,
            $client->readTransaction(static fn () => 'unused')
        );
    }

    public function testBeginTransactionRecoversAfterConnectionPoolExceptionByResettingDriver(): void
    {
        $failingSessionMock = $this->createMock(SessionInterface::class);
        $successfulSessionMock = $this->createMock(SessionInterface::class);
        $firstDriverMock = $this->createMock(DriverInterface::class);
        $secondDriverMock = $this->createMock(DriverInterface::class);
        $driverSetupManager = $this->createMock(DriverSetupManager::class);

        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $firstDriverMock->method('createSession')->willReturn($failingSessionMock);
        $failingSessionMock->method('beginTransaction')
            ->willThrowException(new ConnectionPoolException('Cannot begin on stale session'));

        $expectedTsx = $this->createMock(UnmanagedTransactionInterface::class);
        $secondDriverMock->method('createSession')->willReturn($successfulSessionMock);
        $successfulSessionMock->method('beginTransaction')->willReturn($expectedTsx);

        $driverSetupManager->method('getDefaultAlias')->willReturn('default');
        $driverSetupManager->expects($this->exactly(2))
            ->method('getDriver')
            ->with($sessionConfig, 'default')
            ->willReturnOnConsecutiveCalls($firstDriverMock, $secondDriverMock);
        $driverSetupManager->expects($this->once())->method('resetDriver')->with('default');

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->assertSame($expectedTsx, $client->beginTransaction());
    }
}
