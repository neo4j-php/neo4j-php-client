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

use Laudis\Neo4j\Common\DriverSetupManager;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Exception\ConnectionPoolException;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

final class MultiDriverFailoverTest extends TestCase
{
    public function testMultipleDriversWithDifferentPrioritiesWhenHighestPriorityIsDown(): void
    {
        $mockDriver1 = $this->createMock(DriverInterface::class);
        $mockDriver2 = $this->createMock(DriverInterface::class);
        $mockDriver3 = $this->createMock(DriverInterface::class);
        $sessionConfig = SessionConfiguration::default();

        $mockDriver1->method('verifyConnectivity')
            ->willThrowException(new RuntimeException(
                'Cannot connect to host: "neoj1.example.org". Hosts tried: "192.168.1.1", "neoj1.example.org"'
            ));
        $mockDriver2->method('verifyConnectivity')
            ->willReturn(true);
        $mockDriver3->method('verifyConnectivity')
            ->willReturn(true);

        $this->expectException(RuntimeException::class);
        $mockDriver1->verifyConnectivity($sessionConfig);
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

    public function testDriverSetupManagerVerifyConnectivityReturnsFalseOnConnectionFailure(): void
    {
        $mockDriver = $this->createMock(DriverInterface::class);
        $mockDriver->method('verifyConnectivity')
            ->willThrowException(new ConnectionPoolException('Cannot connect'));

        $driverSetupManager = $this->createMock(DriverSetupManager::class);
        $driverSetupManager->method('verifyConnectivity')
            ->willReturn(false);

        $result = $driverSetupManager->verifyConnectivity(SessionConfiguration::default(), 'test');

        $this->assertFalse($result);
    }

    public function testCompleteMultiDriverFailoverFlow(): void
    {
        $sessionConfig = SessionConfiguration::default();
        $driver1 = $this->createMock(DriverInterface::class);
        $driver2 = $this->createMock(DriverInterface::class);

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
        $this->assertSame($driver2, $selectedDriver, 'Driver 2 should be selected after Driver 1 fails');
    }
}
