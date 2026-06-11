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

final class TelemetryApi
{
    public const TRANSACTION_FUNCTION = 0;
    public const UNMANAGED_TRANSACTION = 1;
    public const SESSION_RUN = 2;
    public const EXECUTE_QUERY = 3;
}
