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
use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Laudis\Neo4j\Contracts\AuthenticateInterface;
use RuntimeException;

class ProtocolFactory
{
    /**
     * @return array{0: V4_4|V5, 1: array{server: string, connection_id: string, hints: list}}
     */
    public function createProtocol(IConnection $connection, AuthenticateInterface $auth, string $userAgent): array
    {
        $bolt = new Bolt($connection);
        $bolt->setProtocolVersions(5, 4.4);

        $protocol = $bolt->build();

        if (!$protocol instanceof V4_4 && !$protocol instanceof V5) {
            throw new RuntimeException('Client only supports bolt version 4.4.* and ^5.0');
        }

        $response = $auth->authenticateBolt($protocol, $userAgent);

        return [$protocol, $response];
    }
}
