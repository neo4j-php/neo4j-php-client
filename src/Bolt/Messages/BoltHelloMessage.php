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

use Bolt\error\BoltException;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\BoltMessage;
use Psr\Log\LogLevel;

final class BoltHelloMessage extends BoltMessage
{
    /**
     * Constructor for the BoltHelloMessage.
     *
     * @param array<string, mixed> $metadata The metadata for the HELLO message (like user agent, supported versions)
     * @param Neo4jLogger|null     $logger   Optional logger for debugging purposes
     */
    public function __construct(
        BoltConnection $connection,
        private readonly array $metadata,
        private readonly ?Neo4jLogger $logger = null,
    ) {
        parent::__construct($connection);
    }

    /**
     * Sends the HELLO message to the server.
     *
     * @throws BoltException
     */
    public function send(): BoltHelloMessage
    {
        $this->logger?->log(LogLevel::DEBUG, 'HELLO', $this->metadata);

        $this->connection->protocol()->hello($this->metadata);

        return $this;
    }
}
