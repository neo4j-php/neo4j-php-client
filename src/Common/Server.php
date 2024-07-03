<?php

namespace Laudis\Neo4j\Common;

use Laudis\Neo4j\Enum\RoutingRoles;

class Server {
    public function __construct(
        public readonly array $addresses,
        public readonly RoutingRoles $role
    ) {

    }
}
