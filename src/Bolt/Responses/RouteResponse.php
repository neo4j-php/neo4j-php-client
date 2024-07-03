<?php

namespace Laudis\Neo4j\Bolt\Responses;

use Laudis\Neo4j\Common\RoutingTable;

class RouteResponse {

    public function __construct(
        public readonly RoutingTable $rt
    ) {

    }
}
