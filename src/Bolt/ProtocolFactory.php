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
use Bolt\protocol\V4_2;
use Bolt\protocol\V4_3;
use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Bolt\protocol\V5_4;
use RuntimeException;

class ProtocolFactory
{
    public function createProtocol(IConnection $connection): V4_2|V4_3|V4_4|V5|V5_1|V5_2|V5_3|V5_4
    {
        $boltOptoutEnv = getenv('BOLT_ANALYTICS_OPTOUT');
        if ($boltOptoutEnv === false) {
            putenv('BOLT_ANALYTICS_OPTOUT=1');
        }

        $bolt = new Bolt($connection);
        // Four Bolt version suggestions (library maximum); include 4.2/4.3 for TestKit stubs and older servers.
        $bolt->setProtocolVersions('5.4.4', '4.4.4', '4.3.3', '4.2.2');
        $protocol = $bolt->build();

        if (!($protocol instanceof V4_2 || $protocol instanceof V4_3 || $protocol instanceof V4_4 || $protocol instanceof V5 || $protocol instanceof V5_1 || $protocol instanceof V5_2 || $protocol instanceof V5_3 || $protocol instanceof V5_4)) {
            throw new RuntimeException('Client only supports Bolt protocol 4.2 through 5.4');
        }

        return $protocol;
    }
}
