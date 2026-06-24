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

namespace Laudis\Neo4j;

use function array_keys;

use Laudis\Neo4j\Contracts\BookmarkManagerInterface;
use Laudis\Neo4j\Databags\Bookmark;

use function sort;

/**
 * A basic {@see BookmarkManagerInterface} implementation.
 */
final class Neo4jBookmarkManager implements BookmarkManagerInterface
{
    /** @var array<string, true> */
    private array $bookmarks;

    /**
     * @param list<Bookmark>                      $initialBookmarks
     * @param callable(list<Bookmark>): void|null $updateListener
     * @param callable(): list<Bookmark>|null     $bookmarksSupplier
     */
    public function __construct(
        array $initialBookmarks,
        private readonly mixed $updateListener = null,
        private readonly mixed $bookmarksSupplier = null,
    ) {
        $this->bookmarks = self::toValueSet($initialBookmarks);
    }

    public function updateBookmarks(array $previousBookmarks, array $newBookmarks): void
    {
        foreach (self::bookmarkValues($previousBookmarks) as $value) {
            unset($this->bookmarks[$value]);
        }

        foreach (self::bookmarkValues($newBookmarks) as $value) {
            $this->bookmarks[$value] = true;
        }

        if ($this->updateListener !== null) {
            ($this->updateListener)($this->toBookmarkList($this->bookmarks));
        }
    }

    public function getBookmarks(): array
    {
        $bookmarks = $this->bookmarks;

        if ($this->bookmarksSupplier !== null) {
            foreach (self::bookmarkValues(($this->bookmarksSupplier)()) as $value) {
                $bookmarks[$value] = true;
            }
        }

        return $this->toBookmarkList($bookmarks);
    }

    /**
     * @param list<Bookmark> $bookmarks
     *
     * @return array<string, true>
     */
    private static function toValueSet(array $bookmarks): array
    {
        $set = [];
        foreach (self::bookmarkValues($bookmarks) as $value) {
            $set[$value] = true;
        }

        return $set;
    }

    /**
     * @param list<Bookmark> $bookmarks
     *
     * @return list<string>
     */
    private static function bookmarkValues(array $bookmarks): array
    {
        return Bookmark::from($bookmarks)->values();
    }

    /**
     * @param array<string, true> $values
     *
     * @return list<Bookmark>
     */
    private function toBookmarkList(array $values): array
    {
        $strings = array_keys($values);
        sort($strings);

        return array_map(static fn (string $value): Bookmark => new Bookmark([$value]), $strings);
    }
}
