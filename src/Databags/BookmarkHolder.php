<?php
declare(strict_types=1);

namespace Laudis\Neo4j\Databags;

final class BookmarkHolder
{
    private Bookmark $bookmark;

    public function __construct(?Bookmark $bookmark = null)
    {
        $this->bookmark = $bookmark ?? new Bookmark();
    }

    public function getBookmark(): Bookmark
    {
        return $this->bookmark;
    }

    public function setBookmark(Bookmark $bookmark): void
    {
        $this->bookmark = $bookmark;
    }
}
