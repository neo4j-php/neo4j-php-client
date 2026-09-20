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

use InvalidArgumentException;
use Laudis\Neo4j\Databags\DriverConfiguration;
use PHPUnit\Framework\TestCase;

final class DriverConfigurationLivenessTest extends TestCase
{
    public function testDefaultLivenessTimeoutIsSixtySeconds(): void
    {
        $config = DriverConfiguration::default();

        self::assertSame(
            DriverConfiguration::DEFAULT_CONNECTION_LIVENESS_CHECK_TIMEOUT,
            $config->getConnectionLivenessCheckTimeout()
        );
        self::assertSame(60.0, $config->getConnectionLivenessCheckTimeout());
    }

    public function testWithConnectionLivenessCheckTimeout(): void
    {
        $config = DriverConfiguration::default()->withConnectionLivenessCheckTimeout(30.0);

        self::assertSame(30.0, $config->getConnectionLivenessCheckTimeout());
    }

    public function testNullDisablesLivenessCheck(): void
    {
        $config = DriverConfiguration::default()->withConnectionLivenessCheckTimeout(null);

        self::assertNull($config->getConnectionLivenessCheckTimeout());
    }

    public function testZeroMeansAlwaysProbe(): void
    {
        $config = DriverConfiguration::default()->withConnectionLivenessCheckTimeout(0.0);

        self::assertSame(0.0, $config->getConnectionLivenessCheckTimeout());
    }

    public function testNegativeTimeoutIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DriverConfiguration::default()->withConnectionLivenessCheckTimeout(-1.0);
    }
}
