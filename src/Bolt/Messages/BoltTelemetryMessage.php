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

use Bolt\enum\Message;
use Bolt\protocol\AProtocol;
use Bolt\protocol\V5_4;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\BoltMessage;
use Laudis\Neo4j\Enum\TelemetryAPI;
use Psr\Log\LogLevel;
use ReflectionClass;

final class BoltTelemetryMessage extends BoltMessage
{
    public function __construct(
        BoltConnection $connection,
        private readonly TelemetryAPI $api,
        private readonly ?Neo4jLogger $logger,
    ) {
        parent::__construct($connection);
    }

    public function send(): BoltTelemetryMessage
    {
        $protocol = $this->connection->protocol();
        if (!$protocol instanceof V5_4) {
            return $this;
        }

        $this->logger?->log(LogLevel::DEBUG, 'TELEMETRY', ['api' => $this->api->value]);
        $protocol->telemetry($this->api->value);
        $this->discardFromPipeline($protocol);

        return $this;
    }

    /**
     * TELEMETRY is fire-and-forget and has no server response, but php-bolt queues it in
     * pipelinedMessages. Leaving it there causes the next response (e.g. BEGIN SUCCESS) to
     * be consumed as TELEMETRY and desynchronizes the protocol.
     */
    private function discardFromPipeline(AProtocol $protocol): void
    {
        $property = (new ReflectionClass($protocol))->getProperty('pipelinedMessages');
        $messages = $property->getValue($protocol);
        if ($messages !== [] && end($messages) === Message::TELEMETRY) {
            array_pop($messages);
            $property->setValue($protocol, $messages);
        }
    }
}
