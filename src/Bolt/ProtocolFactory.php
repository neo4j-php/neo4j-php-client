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
use Bolt\protocol\V3;
use Bolt\protocol\V4;
use Bolt\protocol\V4_1;
use Bolt\protocol\V4_2;
use Bolt\protocol\V4_3;
use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use Bolt\protocol\V6;
use RuntimeException;

class ProtocolFactory
{
    /**
     * Bolt 4.3+ range proposal: 4.4 and the three minors below (4.3, 4.2, 4.1) in one uint32 (00 03 04 04).
     *
     * @see https://neo4j.com/docs/bolt/current/bolt/handshake/
     */
    private const HANDSHAKE_BOLT_4_4_DOWN_TO_4_1 = 0x00030404;

    public function createProtocol(IConnection $connection): V3|V4|V4_1|V4_2|V4_3|V4_4|V5|V5_1|V5_2|V5_3|V5_4|V6
    {
        $boltOptoutEnv = getenv('BOLT_ANALYTICS_OPTOUT');
        if ($boltOptoutEnv === false) {
            putenv('BOLT_ANALYTICS_OPTOUT=1');
        }

        $bolt = new Bolt($connection);
        // Newest first: 6, 5.4.4, Bolt 4.4–4.1 range (single uint32), 3.0 — fits 4 slots and satisfies 4.2 / 4.3 stubs
        $bolt->setProtocolVersions(6, '5.4.4', self::HANDSHAKE_BOLT_4_4_DOWN_TO_4_1, '3.0');
        $protocol = $bolt->build();

        if (!($protocol instanceof V3 || $protocol instanceof V4 || $protocol instanceof V4_1 || $protocol instanceof V4_2 || $protocol instanceof V4_3 || $protocol instanceof V4_4 || $protocol instanceof V5 || $protocol instanceof V5_1 || $protocol instanceof V5_2 || $protocol instanceof V5_3 || $protocol instanceof V5_4 || $protocol instanceof V6)) {
            throw new RuntimeException('Client only supports bolt version 3.0 to 6.x');
        }

        return $protocol;
    }
}
