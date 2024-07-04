<?php

namespace Laudis\Neo4j\Bolt\Responses;

use Laudis\Neo4j\Databags\Bookmark;

class CommitResponse {
    public function __construct(public readonly Bookmark|null $bookmark)
    {

    }
}
