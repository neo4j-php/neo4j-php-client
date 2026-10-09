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

namespace Laudis\Neo4j\Common;

use function microtime;

/**
 * Process-wide clock used for connection idle time.
 *
 * TestKit can freeze it with FakeTimeInstall and advance it with FakeTimeTick.
 * Outside those requests it follows microtime().
 */
final class MonotonicClock
{
    private static bool $installed = false;

    private static float $frozenAt = 0.0;

    private static float $offsetSeconds = 0.0;

    public static function now(): float
    {
        if (self::$installed) {
            return self::$frozenAt + self::$offsetSeconds;
        }

        return microtime(true);
    }

    public static function install(): void
    {
        self::$frozenAt = microtime(true);
        self::$offsetSeconds = 0.0;
        self::$installed = true;
    }

    public static function tick(int $incrementMs): void
    {
        if (!self::$installed) {
            return;
        }

        self::$offsetSeconds += $incrementMs / 1000;
    }

    public static function uninstall(): void
    {
        self::$installed = false;
        self::$offsetSeconds = 0.0;
    }
}
