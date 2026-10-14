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
    private MonotonicClock $clock;

    protected function setUp(): void
    {
        $this->clock = new MonotonicClock();
    }

    public function testNowFollowsRealTimeWhenNotInstalled(): void
    {
        $before = microtime(true);
        $now = $this->clock->now();
        $after = microtime(true);

        self::assertGreaterThanOrEqual($before, $now);
        self::assertLessThanOrEqual($after, $now);
    }

    public function testTickAdvancesFrozenClock(): void
    {
        $this->clock->install();
        $start = $this->clock->now();

        $this->clock->tick(2500);

        self::assertEqualsWithDelta(2.5, $this->clock->now() - $start, 0.000_001);
    }

    public function testTickIsIgnoredUntilInstalled(): void
    {
        $before = microtime(true);
        $this->clock->tick(60_000);
        $now = $this->clock->now();

        self::assertLessThan(1.0, $now - $before);
    }

    public function testConnectionIdleTimeFollowsFakeClock(): void
    {
        $connection = (new ReflectionClass(BoltConnection::class))->newInstanceWithoutConstructor();
        (new ReflectionClass(BoltConnection::class))->getProperty('clock')->setValue($connection, $this->clock);

        $this->clock->install();
        $connection->touch();
        self::assertEqualsWithDelta(0.0, $connection->getIdleTimeSeconds(), 0.000_001);

        $this->clock->tick(5000);

        self::assertEqualsWithDelta(5.0, $connection->getIdleTimeSeconds(), 0.000_001);
    }

    public function testSeparateInstancesDoNotShareFakeTime(): void
    {
        $other = new MonotonicClock();
        $this->clock->install();
        $this->clock->tick(5000);

        $before = microtime(true);
        $now = $other->now();

        self::assertLessThan(1.0, $now - $before);
    }
}
