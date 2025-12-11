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
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ClientSessionExceptionHandlingTest extends TestCase
{
    /**
     * Mock the session and trigger errors when running queries on the client.
     */
    public function testClientRunThrowsExceptionFromSession(): void
    {
        $sessionMock = $this->createMock(SessionInterface::class);
        $driverMock = $this->createMock(DriverInterface::class);
        $driverSetupManager = $this->createMock(DriverSetupManager::class);

        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $driverSetupManager->method('getDefaultAlias')
            ->willReturn('default');

        $driverMock->method('createSession')
            ->willReturn($sessionMock);

        $driverSetupManager->method('getDriver')
            ->willReturn($driverMock);

        $sessionMock->method('runStatements')
            ->willThrowException(new RuntimeException('Session connection lost'));

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Session connection lost');

        $client->run('RETURN 1 as n');
    }

    /**
     * Mock the session and trigger errors when running multiple statements.
     */
    public function testClientRunStatementsThrowsExceptionFromSession(): void
    {
        $sessionMock = $this->createMock(SessionInterface::class);
        $driverMock = $this->createMock(DriverInterface::class);
        $driverSetupManager = $this->createMock(DriverSetupManager::class);

        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $driverSetupManager->method('getDefaultAlias')
            ->willReturn('default');

        $driverMock->method('createSession')
            ->willReturn($sessionMock);

        $driverSetupManager->method('getDriver')
            ->willReturn($driverMock);

        $sessionMock->method('runStatements')
            ->willThrowException(new RuntimeException('Session timeout during query execution'));

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Session timeout');

        $client->runStatements([
            Statement::create('RETURN 1'),
            Statement::create('RETURN 2'),
        ]);
    }

    /**
     * Mock the session and trigger errors on write transaction.
     */
    public function testClientWriteTransactionThrowsExceptionFromSession(): void
    {
        $sessionMock = $this->createMock(SessionInterface::class);
        $driverMock = $this->createMock(DriverInterface::class);
        $driverSetupManager = $this->createMock(DriverSetupManager::class);

        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $driverSetupManager->method('getDefaultAlias')
            ->willReturn('default');

        $driverMock->method('createSession')
            ->willReturn($sessionMock);

        $driverSetupManager->method('getDriver')
            ->willReturn($driverMock);

        $sessionMock->method('writeTransaction')
            ->willThrowException(new RuntimeException('Cannot acquire write lock'));

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot acquire write lock');

        $client->writeTransaction(function () {
            return 'result';
        });
    }

    /**
     * Mock the session and trigger errors on read transaction.
     */
    public function testClientReadTransactionThrowsExceptionFromSession(): void
    {
        $sessionMock = $this->createMock(SessionInterface::class);
        $driverMock = $this->createMock(DriverInterface::class);
        $driverSetupManager = $this->createMock(DriverSetupManager::class);

        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $driverSetupManager->method('getDefaultAlias')
            ->willReturn('default');

        $driverMock->method('createSession')
            ->willReturn($sessionMock);

        $driverSetupManager->method('getDriver')
            ->willReturn($driverMock);

        $sessionMock->method('readTransaction')
            ->willThrowException(new RuntimeException('Database unavailable for reads'));

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Database unavailable for reads');

        $client->readTransaction(function () {
            return 'result';
        });
    }

    /**
     * Mock the session and trigger errors on begin transaction.
     */
    public function testClientBeginTransactionThrowsExceptionFromSession(): void
    {
        $sessionMock = $this->createMock(SessionInterface::class);
        $driverMock = $this->createMock(DriverInterface::class);
        $driverSetupManager = $this->createMock(DriverSetupManager::class);

        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $driverSetupManager->method('getDefaultAlias')
            ->willReturn('default');

        $driverMock->method('createSession')
            ->willReturn($sessionMock);

        $driverSetupManager->method('getDriver')
            ->willReturn($driverMock);

        $sessionMock->method('beginTransaction')
            ->willThrowException(new RuntimeException('Session disconnected during transaction begin'));

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Session disconnected');

        $client->beginTransaction();
    }

    /**
     * Mock the session and trigger errors with a specific alias.
     */
    public function testClientSessionErrorWithAlias(): void
    {
        $sessionMock = $this->createMock(SessionInterface::class);
        $driverMock = $this->createMock(DriverInterface::class);
        $driverSetupManager = $this->createMock(DriverSetupManager::class);

        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $driverMock->method('createSession')
            ->willReturn($sessionMock);

        $driverSetupManager->method('getDriver')
        ->with($sessionConfig, 'secondary')
        ->willReturn($driverMock);

        $sessionMock->method('runStatements')
            ->willThrowException(new RuntimeException('Secondary driver session failed'));

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Secondary driver session failed');

        $client->run('RETURN 1', [], 'secondary');
    }

    public function testClientDoesNotRetryOnSessionFailure(): void
    {
        $sessionMock = $this->createMock(SessionInterface::class);
        $driverMock = $this->createMock(DriverInterface::class);
        $driverSetupManager = $this->createMock(DriverSetupManager::class);

        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $driverSetupManager->method('getDefaultAlias')
            ->willReturn('default');

        $driverSetupManager->expects($this->once())
            ->method('getDriver')
            ->willReturn($driverMock);

        $driverMock->method('createSession')
            ->willReturn($sessionMock);

        $sessionMock->method('runStatements')
            ->willThrowException(new RuntimeException('Session connection lost'));

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Session connection lost');

        $client->run('RETURN 1 as n');
    }
}
