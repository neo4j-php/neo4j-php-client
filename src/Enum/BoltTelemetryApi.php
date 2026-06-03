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

namespace Laudis\Neo4j\Enum;

/**
 * Bolt 5.4 TELEMETRY message API identifiers.
 *
 * @see https://neo4j.com/docs/bolt/current/bolt/message/#messages-telemetry
 */
enum BoltTelemetryApi: int
{
    /** Managed transaction (executeRead / executeWrite). */
    case MANAGED_TRANSACTION = 0;
    /** Explicit transaction (beginTransaction). */
    case EXPLICIT_TRANSACTION = 1;
    /** Autocommit (session.run). */
    case AUTOCOMMIT = 2;
    /** Driver-level (executeQuery). */
    case DRIVER_EXECUTE_QUERY = 3;
}
