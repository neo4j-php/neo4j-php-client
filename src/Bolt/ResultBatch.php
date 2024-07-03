<?php

namespace Laudis\Neo4j\Bolt;

use Laudis\Neo4j\Bolt\Responses\Record;
use Laudis\Neo4j\Bolt\Responses\ResultSuccessResponse;

class ResultBatch {
    /**
     * @param list<Record> $records
     * @param ResultSuccessResponse $response
     */
    public function __construct(public readonly array $records, public readonly ResultSuccessResponse $response)
    {

    }
}
