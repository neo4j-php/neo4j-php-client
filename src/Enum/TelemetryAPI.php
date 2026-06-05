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
 * Bolt TELEMETRY message API identifiers (Bolt 5.4+).
 */
enum TelemetryAPI: int
{
    case TX_FUNC = 0;
    case TX = 1;
    case AUTO_COMMIT = 2;
    case DRIVER = 3;
}
