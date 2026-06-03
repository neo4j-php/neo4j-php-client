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
use Bolt\protocol\V5_4;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\BoltMessage;
use Laudis\Neo4j\Enum\BoltTelemetryApi;
use Psr\Log\LogLevel;
use ReflectionClass;
use RuntimeException;

final class BoltTelemetryMessage extends BoltMessage
{
    public function __construct(
        BoltConnection $connection,
        private readonly BoltTelemetryApi $api,
        private readonly ?Neo4jLogger $logger,
    ) {
        parent::__construct($connection);
    }

    public function send(): BoltTelemetryMessage
    {
        $protocol = $this->connection->protocol();
        if (!$protocol instanceof V5_4) {
            throw new RuntimeException('TELEMETRY requires Bolt protocol 5.4');
        }

        $this->logger?->log(LogLevel::DEBUG, 'TELEMETRY', ['api' => $this->api->value]);
        $protocol->telemetry($this->api->value);
        $this->discardPipelinedTelemetryResponse($protocol);

        return $this;
    }

    /**
     * TELEMETRY is sent immediately before BEGIN/RUN; the server may not respond until later.
     * Do not leave TELEMETRY in the pipelined queue or the next getResponse() will block.
     */
    private function discardPipelinedTelemetryResponse(V5_4 $protocol): void
    {
        $reflection = new ReflectionClass($protocol);
        $property = $reflection->getProperty('pipelinedMessages');
        /** @var list<Message> $pipelined */
        $pipelined = $property->getValue($protocol);
        if ($pipelined !== [] && end($pipelined) === Message::TELEMETRY) {
            array_pop($pipelined);
            $property->setValue($protocol, $pipelined);
        }
    }
}
