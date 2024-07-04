<?php

namespace Laudis\Neo4j\Common;

class RoutingTable {
    /**
     * @param list<Server>
     */
    public function __construct(
        public readonly int $ttl,
        public readonly string $db,
        public readonly array $servers
    ) {

    }
}
