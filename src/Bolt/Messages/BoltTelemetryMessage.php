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

namespace Laudis\Neo4j\Bolt\Messages;

use Bolt\protocol\V5_4;
use Bolt\protocol\V5_6;
use Bolt\protocol\V5_7;
use Bolt\protocol\V5_8;
use Bolt\protocol\V6;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\TelemetryAPIEnum;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\BoltMessage;
use LogicException;
use Psr\Log\LogLevel;

final class BoltTelemetryMessage extends BoltMessage
{
    public function __construct(
        BoltConnection $connection,
        private readonly TelemetryAPIEnum $api,
        private readonly ?Neo4jLogger $logger,
    ) {
        parent::__construct($connection);
    }

    public function send(): BoltTelemetryMessage
    {
        $this->logger?->log(LogLevel::DEBUG, 'TELEMETRY', ['api' => $this->api->value]);

        $protocol = $this->connection->protocol();
        if (!$protocol instanceof V5_4 && !$protocol instanceof V5_6 && !$protocol instanceof V5_7 && !$protocol instanceof V5_8 && !$protocol instanceof V6) {
            throw new LogicException('Telemetry requires Bolt protocol 5.4 or newer');
        }

        $protocol->telemetry($this->api->value);

        return $this;
    }
}
