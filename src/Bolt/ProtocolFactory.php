<?php

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Bolt;

use Bolt\Bolt;
use Bolt\connection\IConnection;
use Bolt\error\ConnectException;
use Bolt\error\MessageException;
use Bolt\protocol\V3;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use Laudis\Neo4j\Exception\Neo4jException;
use RuntimeException;

class ProtocolFactory
{
    /**
     * @return array{0: V3, 1: array{server: string, connection_id: string, hints: list}}
     */
    public function createProtocol(IConnection $connection, AuthenticateInterface $auth, string $userAgent): array
    {
        $bolt = new Bolt($connection);
        try {
            $bolt->setProtocolVersions(4.4, 4.3, 4.2, 3);
            try {
                $protocol = $bolt->build();
            } catch (ConnectException $exception) {
                $bolt->setProtocolVersions(4.1, 4.0, 4, 3);
                $protocol = $bolt->build();
            }

            if (!$protocol instanceof V3) {
                throw new RuntimeException('Client only supports bolt version ^3.0 and ^4.0.');
            }

            $response = $auth->authenticateBolt($protocol, $userAgent);
        } catch (MessageException $e) {
            throw Neo4jException::fromMessageException($e);
        }

        return [$protocol, $response];
    }
}
