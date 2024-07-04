<?php

namespace Laudis\Neo4j\Bolt\Responses;

use Bolt\protocol\IStructure;

class Record {
    /**
     * @param list<int|float|bool|string|IStructure|array> $values
     */
    public function __construct(
        public readonly array $values
    )
    {

    }
}
