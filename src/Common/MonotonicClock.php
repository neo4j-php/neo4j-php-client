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
 * Clock used for connection idle time.
 *
 * Share one instance across a driver when time must be controlled.
 * TestKit freezes that instance with install() and advances it with tick().
 * Until install() is called, now() follows microtime().
 */
final class MonotonicClock
{
    private bool $installed = false;

    private float $frozenAt = 0.0;

    private float $offsetSeconds = 0.0;

    public function now(): float
    {
        if ($this->installed) {
            return $this->frozenAt + $this->offsetSeconds;
        }

        return microtime(true);
    }

    public function install(): void
    {
        $this->frozenAt = microtime(true);
        $this->offsetSeconds = 0.0;
        $this->installed = true;
    }

    public function tick(int $incrementMs): void
    {
        if (!$this->installed) {
            return;
        }

        $this->offsetSeconds += $incrementMs / 1000;
    }

    public function uninstall(): void
    {
        $this->installed = false;
        $this->offsetSeconds = 0.0;
    }
}
