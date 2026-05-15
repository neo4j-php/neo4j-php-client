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
    /** @var int Number of open explicit Bolt transactions using this holder with a Neo4j bookmark manager. */
    private int $neo4jSharedManagedBoltTxDepth = 0;

    private bool $neo4jMergeCommittedServerBookmarks = false;

    public function __construct(
        private Bookmark $bookmark,
    ) {
    }

    /**
     * @psalm-mutation-free
     */
    public function getBookmark(): Bookmark
    {
        return $this->bookmark;
    }

    public function setBookmark(Bookmark $bookmark): void
    {
        $this->bookmark = $bookmark;
    }

    /**
     * Neo4j bookmark manager: an explicit Bolt transaction was opened on this shared holder.
     */
    public function neo4jSharedManagedTransactionOpened(): void
    {
        if ($this->neo4jSharedManagedBoltTxDepth > 0) {
            $this->neo4jMergeCommittedServerBookmarks = true;
        }
        ++$this->neo4jSharedManagedBoltTxDepth;
    }

    /**
     * Neo4j bookmark manager: an explicit Bolt transaction ended (commit or rollback).
     */
    public function neo4jSharedManagedTransactionClosed(): void
    {
        if ($this->neo4jSharedManagedBoltTxDepth > 0) {
            --$this->neo4jSharedManagedBoltTxDepth;
        }
        if ($this->neo4jSharedManagedBoltTxDepth === 0) {
            $this->neo4jMergeCommittedServerBookmarks = false;
        }
    }

    /**
     * Apply bookmark returned from COMMIT when using a Neo4j shared bookmark holder.
     * Sequential work replaces the set; overlapping transactions merge new server bookmarks.
     */
    public function neo4jApplyCommittedServerBookmark(Bookmark $incoming): void
    {
        if ($incoming->isEmpty()) {
            return;
        }
        if ($this->neo4jMergeCommittedServerBookmarks) {
            $this->bookmark = Bookmark::from([$this->bookmark, $incoming]);
        } else {
            $this->bookmark = $incoming;
        }
    }
}
