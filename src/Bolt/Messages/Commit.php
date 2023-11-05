<?php

namespace Laudis\Neo4j\Bolt\Messages;

use Bolt\protocol\V4_4;
use Bolt\protocol\V5;
use Bolt\protocol\V5_1;
use Bolt\protocol\V5_2;
use Bolt\protocol\V5_3;
use Laudis\Neo4j\Contracts\MessageInterface;

class Commit implements MessageInterface
{
    public function send(V4_4|V5|V5_2|V5_1|V5_3 $bolt): void
    {
        $bolt->commit();
    }
}
