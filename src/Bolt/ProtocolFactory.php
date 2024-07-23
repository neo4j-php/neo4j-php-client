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

use Bolt\Bolt;
use Bolt\connection\IConnection;
use Bolt\error\ConnectException;
use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use RuntimeException;

class ProtocolFactory
{
    /**
     * @return array{0: V4_4|V5|V5_1|V5_2|V5_3|V5_4, 1: array{server: string, connection_id: string, hints: list}}
     */
    public function createProtocol(IConnection $connection, AuthenticateInterface $auth, string $userAgent): array
    {
        $bolt = new Bolt($connection);
        $bolt->setProtocolVersions(5.4, 5.3, 5, 4.4);

        try {
            $protocol = $bolt->build();
        } catch (ConnectException $e) {
            // Assume incorrect protocol version
            $bolt->setProtocolVersions(5.2, 5.1);
            $protocol = $bolt->build();
        }

        if (!($protocol instanceof V4_4 || $protocol instanceof V5 || $protocol instanceof V5_1 || $protocol instanceof V5_2 || $protocol instanceof V5_3 || $protocol instanceof V5_4)) {
            throw new RuntimeException('Client only supports bolt version 4.4 and ^5.0');
        }

        $response = $auth->authenticateBolt($protocol, $userAgent);

        return [$protocol, $response];
    }
}
