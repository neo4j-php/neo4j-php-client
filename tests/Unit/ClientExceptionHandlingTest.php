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
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
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
}
