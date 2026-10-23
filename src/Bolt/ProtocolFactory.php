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
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use Bolt\protocol\V5_6;
use Bolt\protocol\V5_7;
use Bolt\protocol\V5_8;
use Bolt\protocol\V6;
use RuntimeException;

class ProtocolFactory
{
    public function createProtocol(IConnection $connection): V4_4|V5|V5_1|V5_2|V5_3|V5_4|V5_6|V5_7|V5_8|V6
    {
        $boltOptoutEnv = getenv('BOLT_ANALYTICS_OPTOUT');
        if ($boltOptoutEnv === false) {
            putenv('BOLT_ANALYTICS_OPTOUT=1');
        }

        $bolt = new Bolt($connection);
        // php-bolt-driver 7.5 selects PackStream from the negotiated Bolt version.
        $bolt->setProtocolVersions(...$this->offeredProtocolVersions());
        $protocol = $bolt->build();

        if (!($protocol instanceof V4_4 || $protocol instanceof V5 || $protocol instanceof V5_1 || $protocol instanceof V5_2 || $protocol instanceof V5_3 || $protocol instanceof V5_4 || $protocol instanceof V5_6 || $protocol instanceof V5_7 || $protocol instanceof V5_8 || $protocol instanceof V6)) {
            throw new RuntimeException('Client only supports bolt version 4.4 to 6.1');
        }

        return $protocol;
    }

    /**
     * Bolt versions offered during the handshake.
     *
     * Each entry is major.minor.range. 6.1.1 offers 6.1 and 6.0, 5.8.8 offers 5.8
     * down through 5.0, and 4.4 is exactly 4.4.
     *
     * @return list<string>
     */
    public function offeredProtocolVersions(): array
    {
        return ['6.1.1', '5.8.8', '4.4'];
    }
}
