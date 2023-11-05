<?php

namespace Laudis\Neo4j\Bolt\Messages;

use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Laudis\Neo4j\Contracts\MessageInterface;
use LogicException;

class Logoff implements MessageInterface
{
    public function send(V4_4|V5|V5_2|V5_1|V5_3 $bolt): void
    {
        if ($bolt instanceof V4_4 || $bolt instanceof V5) {
            throw new LogicException('Cannot run logoff on bolt version 5.0 or lower. Version detected: '.$bolt->getVersion());
        }

        $bolt->logoff();
    }
}
