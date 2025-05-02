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
use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\BoltMessage;
use Psr\Log\LogLevel;

final class BoltHelloMessage extends BoltMessage
{
    /**
     * Constructor for the BoltHelloMessage.
     *
     * @param V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol The protocol connection
     * @param array<string, mixed>        $metadata The metadata for the HELLO message (like user agent, supported versions)
     * @param Neo4jLogger|null            $logger   Optional logger for debugging purposes
     */
    public function __construct(
        private readonly V4_4|V5|V5_1|V5_2|V5_3|V5_4 $protocol,
        private readonly array $metadata,
        private readonly ?Neo4jLogger $logger = null,
    ) {
        parent::__construct($protocol);
    }

    /**
     * Sends the HELLO message to the server.
     *
     * @throws BoltException
     */
    public function send(): BoltHelloMessage
    {
        $this->logger?->log(LogLevel::DEBUG, 'HELLO', $this->metadata);

        $this->protocol->hello($this->metadata);

        return $this;
    }
}
