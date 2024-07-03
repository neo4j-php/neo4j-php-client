<?php

namespace Laudis\Neo4j\Bolt\Responses;

class HelloResponse {
    /**
     * @param string $server
     * @param string $connection_id
     * @param list<string> $patch_bolt
     * @param array<string, string|int|float|bool> $hints
     */
    public function __construct(
        public readonly string $server,
        public readonly string $connection_id,
        public readonly array $patch_bolt,
        public readonly array $hints
    ){

    }
}
