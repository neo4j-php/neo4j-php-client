<?php

namespace Laudis\Neo4j\Bolt\Responses;

class RunResponse {
    /**
     * @param non-empty-list<string> $fields
     */
    public function __construct(public readonly array $fields, public readonly int $t_first, public readonly int|null $qid)
    {

    }
}
