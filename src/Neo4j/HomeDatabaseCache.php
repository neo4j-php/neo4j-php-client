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

namespace Laudis\Neo4j\Neo4j;

/**
 * Process-wide cache of the home database name returned by ROUTE for a cluster host.
 *
 * When the session has no database set, subsequent routing table lookups reuse this
 * value so the driver does not need to resolve the home database on every request.
 */
final class HomeDatabaseCache
{
    /** @var array<string, string> */
    private static array $cache = [];

    public static function get(string $host): ?string
    {
        return self::$cache[$host] ?? null;
    }

    public static function set(string $host, string $database): void
    {
        self::$cache[$host] = $database;
    }

    public static function clear(string $host): void
    {
        unset(self::$cache[$host]);
    }

    public static function clearAll(): void
    {
        self::$cache = [];
    }
}
