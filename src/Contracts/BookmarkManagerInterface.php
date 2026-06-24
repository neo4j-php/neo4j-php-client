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

namespace Laudis\Neo4j\Contracts;

use Laudis\Neo4j\Databags\Bookmark;

/**
 * Keeps track of bookmarks and is used by the driver to ensure causal consistency between sessions and query executions.
 *
 * @see SessionConfiguration::withBookmarkManager()
 */
interface BookmarkManagerInterface
{
    /**
     * Updates bookmarks by deleting the given previous bookmarks and adding the new bookmarks.
     *
     * @param list<Bookmark> $previousBookmarks
     * @param list<Bookmark> $newBookmarks
     */
    public function updateBookmarks(array $previousBookmarks, array $newBookmarks): void;

    /**
     * Gets an immutable set of bookmarks.
     *
     * @return list<Bookmark>
     */
    public function getBookmarks(): array;
}
