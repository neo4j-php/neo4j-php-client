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

use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\BoltMessage;
use Psr\Log\LogLevel;

/**
 * A message that issues a LOGOFF request to the server to terminate the connection.
 */
class BoltLogoffMessage extends BoltMessage
{
    /**
     * @param V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol The Bolt protocol version
     * @param Neo4jLogger|null            $logger   Optional logger for logging purposes
     */
    public function __construct(
        private readonly V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol,
        private readonly ?Neo4jLogger $logger = null,
    ) {
        parent::__construct($protocol);
    }

    /**
     * Sends the LOGOFF request to the server to disconnect.
     *
     * @return BoltLogoffMessage The current instance of the message
     */
    public function send(): BoltLogoffMessage
    {
        $this->logger?->log(LogLevel::DEBUG, 'LOGOFF', []);
        /** @psalm-suppress PossiblyUndefinedMethod */
        $this->protocol->logoff();

        return $this;
    }
}
