<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Databags;

final class BookmarkHolder
{
    public function __construct(
        private Bookmark $bookmark
    ) {}

    public function getBookmark(): Bookmark
    {
        return $this->bookmark;
    }

    public function setBookmark(Bookmark $bookmark): void
    {
        $this->bookmark = $bookmark;
    }
}
