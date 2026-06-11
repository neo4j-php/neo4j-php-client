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

use Bolt\enum\Message;
use Bolt\protocol\AProtocol;
use Bolt\protocol\V5_4;
use ReflectionProperty;

/**
 * @internal
 */
final class BoltProtocolPipeline
{
    public static function discardPendingTelemetry(V5_4 $protocol): void
    {
        $property = new ReflectionProperty(AProtocol::class, 'pipelinedMessages');
        /** @var list<Message> $pipelinedMessages */
        $pipelinedMessages = $property->getValue($protocol);
        if ($pipelinedMessages === [] || $pipelinedMessages[0] !== Message::TELEMETRY) {
            return;
        }

        array_shift($pipelinedMessages);
        $property->setValue($protocol, $pipelinedMessages);
    }
}
