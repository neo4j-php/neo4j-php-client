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

/**
 * Optional hooks used when a session is tied to a Neo4j-style bookmark manager
 * (supplier / consumer callbacks).
 */
interface Neo4jBookmarkManagerHooksInterface
{
    /**
     * Additional bookmarks merged only for the next wire message (RUN / BEGIN / ROUTE).
     *
     * @return list<string>
     */
    public function getSupplierBookmarks(): array;

    /**
     * Called after the server reports new bookmark(s) for the session bookmark store.
     *
     * @param list<string> $bookmarks
     */
    public function notifyBookmarksUpdated(array $bookmarks): void;
}
