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

namespace Laudis\Neo4j\Bolt;

/**
 * Telemetry API identifiers sent via Bolt TELEMETRY message.
 */
enum TelemetryAPIEnum: int
{
    case TRANSACTION_FUNCTION = 0;
    case UNMANAGED_TRANSACTION = 1;
    case SESSION_RUN = 2;
    case EXECUTE_QUERY = 3;
}
