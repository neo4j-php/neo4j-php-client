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

namespace Laudis\Neo4j\Bolt;

use Laudis\Neo4j\Contracts\BookmarkManagerInterface;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\BookmarkHolder;

/**
 * Tracks bookmark manager state for a session, matching Java NetworkSession bookmark behaviour.
 */
final class SessionBookmarkTracker
{
    /** @var list<Bookmark> */
    private array $lastUsedBookmarks = [];

    /** @var list<Bookmark> */
    private array $lastReceivedBookmarks;

    /**
     * @param list<Bookmark> $sessionBookmarks
     */
    public function __construct(
        private readonly BookmarkHolder $bookmarkHolder,
        private readonly BookmarkManagerInterface $bookmarkManager,
        array $sessionBookmarks,
    ) {
        $this->lastReceivedBookmarks = $sessionBookmarks;
        $this->syncHolder(false);
    }

    public function prepareForSend(bool $updateLastUsed): void
    {
        $this->syncHolder($updateLastUsed);
    }

    public function handleNewBookmark(Bookmark $bookmark): void
    {
        if ($bookmark->isEmpty()) {
            return;
        }

        $newBookmarks = [new Bookmark($bookmark->values())];
        $this->lastReceivedBookmarks = $newBookmarks;
        $this->bookmarkManager->updateBookmarks($this->lastUsedBookmarks, $newBookmarks);
    }

    /**
     * @return list<string>
     */
    public function getRediscoveryBookmarkValues(): array
    {
        return $this->determineBookmarks(false)->values();
    }

    private function syncHolder(bool $updateLastUsed): void
    {
        $this->bookmarkHolder->setBookmark($this->determineBookmarks($updateLastUsed));
    }

    private function determineBookmarks(bool $updateLastUsed): Bookmark
    {
        $bookmarks = $this->bookmarkManager->getBookmarks();
        if ($updateLastUsed) {
            $this->lastUsedBookmarks = $bookmarks;
        }

        return Bookmark::from([...$bookmarks, ...$this->lastReceivedBookmarks]);
    }
}
