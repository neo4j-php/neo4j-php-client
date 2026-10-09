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

use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Common\MonotonicClock;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class MonotonicClockTest extends TestCase
{
    protected function tearDown(): void
    {
        MonotonicClock::uninstall();
    }

    public function testNowFollowsRealTimeWhenNotInstalled(): void
    {
        $before = microtime(true);
        $now = MonotonicClock::now();
        $after = microtime(true);

        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
    }

    public function testTickAdvancesFrozenClock(): void
    {
        MonotonicClock::install();
        $start = MonotonicClock::now();

        MonotonicClock::tick(2500);

        self::assertEqualsWithDelta(2.5, MonotonicClock::now() - $start, 0.000_001);
    }

    public function testTickIsIgnoredUntilInstalled(): void
    {
        $before = microtime(true);
        MonotonicClock::tick(60_000);
        $now = MonotonicClock::now();

        self::assertLessThan(1.0, $now - $before);
    }

    public function testConnectionIdleTimeFollowsFakeClock(): void
    {
        $connection = (new ReflectionClass(BoltConnection::class))->newInstanceWithoutConstructor();

        MonotonicClock::install();
        $connection->touch();
        self::assertEqualsWithDelta(0.0, $connection->getIdleTimeSeconds(), 0.000_001);

        MonotonicClock::tick(5000);

        self::assertEqualsWithDelta(5.0, $connection->getIdleTimeSeconds(), 0.000_001);
    }
}
