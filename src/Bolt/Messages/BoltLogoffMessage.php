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

use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\BoltMessage;
use Psr\Log\LogLevel;

/**
 * A message that issues a LOGOFF request to the server to terminate the connection.
 */
class BoltLogoffMessage extends BoltMessage
{
    /**
     * @param Neo4jLogger|null $logger Optional logger for logging purposes
     */
    public function __construct(
        BoltConnection $connection,
        private readonly ?Neo4jLogger $logger = null,
    ) {
        parent::__construct($connection);
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
        $this->connection->protocol()->logoff();

        return $this;
    }
}
