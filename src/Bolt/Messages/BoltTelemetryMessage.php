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

use Bolt\enum\Signature;
use Bolt\protocol\V5_4;
use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Contracts\BoltMessage;
use LogicException;
use Psr\Log\LogLevel;
use Throwable;

final class BoltTelemetryMessage extends BoltMessage
{
    public function __construct(
        BoltConnection $connection,
        private readonly int $api,
        private readonly ?Neo4jLogger $logger,
    ) {
        parent::__construct($connection);
    }

    public function send(): BoltTelemetryMessage
    {
        $this->logger?->log(LogLevel::DEBUG, 'TELEMETRY', ['api' => $this->api]);

        $protocol = $this->connection->protocol();
        if (!$protocol instanceof V5_4) {
            throw new LogicException('Telemetry requires Bolt protocol V5.4');
        }

        $protocol->telemetry($this->api);

        return $this;
    }

    public function tryAcknowledge(): bool
    {
        try {
            $this->connection->applyRecvTimeoutTemporarily();

            if ($this->connection->getRecvTimeoutHint() === null && $this->connection->getOriginalTimeout() === null) {
                $currentTimeout = $this->connection->getTimeout();
                $this->connection->setOriginalTimeout($currentTimeout);
                $this->connection->setTimeout($this->connection->getDefaultRecvTimeout());
            }

            $response = $this->connection->protocol()->getResponse();
            $this->connection->restoreOriginalTimeout();

            return $response->signature === Signature::SUCCESS;
        } catch (Throwable) {
            $this->connection->restoreOriginalTimeout();
            $this->connection->discardPendingTelemetryFromPipeline();

            return false;
        }
    }
}
