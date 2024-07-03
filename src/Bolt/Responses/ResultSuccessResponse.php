<?php

namespace Laudis\Neo4j\Bolt\Responses;

use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Enum\QueryTypeEnum;

/**
 * Response when either a Discard was successful, or the records have been successfully consumed after a PULL message
 */
class ResultSuccessResponse {
    /**
     * @param bool $has_more
     * @param Bookmark|null $bookmark
     * @param string|null $db
     * @param list<string>|null $notification
     * @param array<string, string>|null $plan
     * @param array<string, string>|null $profile
     * @param array<string, int|float|string|bool>|null $stats
     * @param QueryTypeEnum|null $type
     */
    public function __construct(
        public readonly bool $has_more,
        public readonly Bookmark|null $bookmark,
        public readonly string|null $db,
        public readonly array|null $notification,
        public readonly array|null $plan,
        public readonly array|null $profile,
        public readonly array|null $stats,
        public readonly int|null $t_last,
        public readonly int|null $t_first,
        public readonly QueryTypeEnum|null $type
    ) {

    }
}
