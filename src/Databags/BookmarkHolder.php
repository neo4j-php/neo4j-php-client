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
    /** @var callable(Bookmark): void|null */
    private $serverBookmarkListener;

    public function __construct(
        private Bookmark $bookmark,
    ) {
    }

    public function getBookmark(): Bookmark
    {
        return $this->bookmark;
    }

    /**
     * Sets the outgoing bookmark to send on the next BEGIN or RUN.
     */
    public function setBookmark(Bookmark $bookmark): void
    {
        $this->bookmark = $bookmark;
    }

    /**
     * @param callable(Bookmark): void|null $listener
     */
    public function onServerBookmark(?callable $listener): void
    {
        $this->serverBookmarkListener = $listener;
    }

    /**
     * Handles an incoming bookmark from the server and propagates it to the BookmarkManager via the registered listener.
     */
    public function setBookmarkFromServer(Bookmark $bookmark): void
    {
        $this->bookmark = $bookmark;

        if ($this->serverBookmarkListener !== null && !$bookmark->isEmpty()) {
            ($this->serverBookmarkListener)($bookmark);
        }
    }
}
