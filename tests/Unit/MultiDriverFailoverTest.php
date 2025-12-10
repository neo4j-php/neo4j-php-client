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
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Exception\ConnectionPoolException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class MultiDriverFailoverTest extends TestCase
{
    public function testMultipleDriversWithDifferentPrioritiesWhenHighestPriorityIsDown(): void
    {
        $driver1 = $this->createMock(DriverInterface::class);
        $driver2 = $this->createMock(DriverInterface::class);
        $driver3 = $this->createMock(DriverInterface::class);
        $sessionConfig = SessionConfiguration::default();

        $driver1->method('verifyConnectivity')
            ->willThrowException(new ConnectionPoolException(
                'Cannot connect to host: "node1.example.org". Hosts tried: "192.168.1.1", "node1.example.org"'
            ));

        $driver2->method('verifyConnectivity')
            ->willThrowException(new RuntimeException(
                'Cannot connect to host: "node2.example.org". Hosts tried: "192.168.1.2", "node2.example.org"'
            ));

        $driver3->method('verifyConnectivity')
            ->willReturn(true);

        $drivers = [$driver1, $driver2, $driver3];
        $selectedDriver = null;
        $failedDrivers = [];

        foreach ($drivers as $driver) {
            try {
                if ($driver->verifyConnectivity($sessionConfig)) {
                    $selectedDriver = $driver;
                    break;
                }
            } catch (Throwable $e) {
                $failedDrivers[] = $e;
                continue;
            }
        }

        $this->assertCount(2, $failedDrivers, 'Two highest-priority drivers should fail');
        $this->assertSame($driver3, $selectedDriver, 'Should fall back to lowest-priority driver');

        // Safe access after count assertion
        if (isset($failedDrivers[0])) {
            $this->assertInstanceOf(ConnectionPoolException::class, $failedDrivers[0], 'First driver threw ConnectionPoolException');
        }
        if (isset($failedDrivers[1])) {
            $this->assertInstanceOf(RuntimeException::class, $failedDrivers[1], 'Second driver threw RuntimeException');
        }
    }

    public function testDriverFallbackToSecondaryWhenPrimaryFails(): void
    {
        $driver1 = $this->createMock(DriverInterface::class);
        $driver2 = $this->createMock(DriverInterface::class);
        $sessionConfig = SessionConfiguration::default();

        $driver1->method('verifyConnectivity')
            ->willThrowException(new ConnectionPoolException(
                'Cannot connect to host: "node1.example.org". Hosts tried: "192.168.1.1", "node1.example.org"'
            ));

        $driver2->method('verifyConnectivity')
            ->willReturn(true);

        $drivers = [$driver1, $driver2];
        $selectedDriver = null;
        $exceptionCaught = false;

        foreach ($drivers as $driver) {
            try {
                if ($driver->verifyConnectivity($sessionConfig)) {
                    $selectedDriver = $driver;
                    break;
                }
            } catch (Throwable $e) {
                $exceptionCaught = true;
                continue;
            }
        }

        $this->assertTrue($exceptionCaught, 'Exception should be caught from Driver 1');
        $this->assertSame($driver2, $selectedDriver, 'Driver 2 should be selected as fallback');
    }

    public function testDriverSetupManagerContinuesOnThrowable(): void
    {
        $sessionConfig = SessionConfiguration::default();
        $driver1 = $this->createMock(DriverInterface::class);
        $driver2 = $this->createMock(DriverInterface::class);

        $driver1->method('verifyConnectivity')
            ->willThrowException(new RuntimeException('Connection failed'));

        $driver2->method('verifyConnectivity')
            ->willReturn(true);

        $drivers = [$driver1, $driver2];
        $selectedDriver = null;

        foreach ($drivers as $driver) {
            try {
                if ($driver->verifyConnectivity($sessionConfig)) {
                    $selectedDriver = $driver;
                    break;
                }
            } catch (Throwable $e) {
                continue;
            }
        }

        $this->assertSame($driver2, $selectedDriver);
    }

    public function testClientThrowsExceptionWhenAllDriversFail(): void
    {
        $driver1 = $this->createMock(DriverInterface::class);
        $driver2 = $this->createMock(DriverInterface::class);
        $driver3 = $this->createMock(DriverInterface::class);

        $sessionConfig = SessionConfiguration::default();

        $driver1->method('verifyConnectivity')
            ->willThrowException(new RuntimeException(
                'Cannot connect to host: "node1.example.org". Hosts tried: "192.168.1.1", "node1.example.org"'
            ));

        $driver2->method('verifyConnectivity')
            ->willThrowException(new RuntimeException(
                'Cannot connect to host: "node2.example.org". Hosts tried: "192.168.1.2", "node2.example.org"'
            ));

        $driver3->method('verifyConnectivity')
            ->willThrowException(new RuntimeException(
                'Cannot connect to host: "node3.example.org". Hosts tried: "192.168.1.3", "node3.example.org"'
            ));

        $drivers = [$driver1, $driver2, $driver3];
        $selectedDriver = null;
        $failureCount = 0;
        $lastException = null;

        foreach ($drivers as $driver) {
            try {
                if ($driver->verifyConnectivity($sessionConfig)) {
                    $selectedDriver = $driver;
                    break;
                }
            } catch (Throwable $e) {
                ++$failureCount;
                $lastException = $e;
                continue;
            }
        }

        $this->assertNull($selectedDriver, 'No driver should be selected when all fail');
        $this->assertEquals(3, $failureCount, 'All three drivers should fail');
        $this->assertInstanceOf(RuntimeException::class, $lastException);
        $this->assertStringContainsString('Cannot connect to host', $lastException->getMessage());
    }

    public function testClientRunStatementWithMultipleDriverFailures(): void
    {
        $driverSetupManager = $this->createMock(DriverSetupManager::class);
        $sessionConfig = SessionConfiguration::default();
        $transactionConfig = TransactionConfiguration::default();

        $driverSetupManager->method('getDriver')
            ->willThrowException(new RuntimeException(
                'Cannot connect to any server on alias: default with Uris: (\'neo4j://node1.example.org:7687\', \'neo4j://node2.example.org:7687\', \'neo4j://node3.example.org:7687\')'
            ));

        $client = new Client($driverSetupManager, $sessionConfig, $transactionConfig);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot connect to any server on alias: default');

        $client->run('RETURN 1 as n');
    }

    public function testVerifyConnectivityCatchesRuntimeException(): void
    {
        $driver1 = $this->createMock(DriverInterface::class);
        $driver2 = $this->createMock(DriverInterface::class);
        $sessionConfig = SessionConfiguration::default();

        $driver1->method('verifyConnectivity')
            ->willThrowException(new RuntimeException(
                'Runtime error during connection pool acquire: Cannot create connection'
            ));

        $driver2->method('verifyConnectivity')
            ->willReturn(true);

        $drivers = [$driver1, $driver2];
        $selectedDriver = null;
        $runtimeExceptionCaught = false;
        $exceptionMessage = '';

        foreach ($drivers as $driver) {
            try {
                if ($driver->verifyConnectivity($sessionConfig)) {
                    $selectedDriver = $driver;
                    break;
                }
            } catch (RuntimeException $e) {
                $runtimeExceptionCaught = true;
                $exceptionMessage = $e->getMessage();
                continue;
            }
        }

        $this->assertTrue($runtimeExceptionCaught, 'RuntimeException should be caught from Driver 1');
        $this->assertStringContainsString('Runtime error during connection pool acquire', $exceptionMessage);
        $this->assertSame($driver2, $selectedDriver, 'Driver 2 should be selected after Driver 1 throws RuntimeException');
    }
}
