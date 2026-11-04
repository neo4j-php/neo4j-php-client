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

/**
 * Configuration used to create a bookmark manager via {@see \Laudis\Neo4j\BookmarkManagers::defaultManager()}.
 *
 * @psalm-immutable
 */
final class BookmarkManagerConfig
{
    /**
     * @param list<Bookmark>                      $initialBookmarks
     * @param callable(list<Bookmark>): void|null $bookmarksConsumer
     * @param callable(): list<Bookmark>|null     $bookmarksSupplier
     */
    public function __construct(
        private readonly array $initialBookmarks = [],
        private readonly mixed $bookmarksConsumer = null,
        private readonly mixed $bookmarksSupplier = null,
    ) {
    }

    public static function default(): self
    {
        return new self();
    }

    /**
     * @param list<Bookmark> $initialBookmarks
     */
    public function withInitialBookmarks(array $initialBookmarks): self
    {
        return new self($initialBookmarks, $this->bookmarksConsumer, $this->bookmarksSupplier);
    }

    /**
     * @param callable(list<Bookmark>): void $bookmarksConsumer
     */
    public function withBookmarksConsumer(callable $bookmarksConsumer): self
    {
        return new self($this->initialBookmarks, $bookmarksConsumer, $this->bookmarksSupplier);
    }

    /**
     * @param callable(): list<Bookmark> $bookmarksSupplier
     */
    public function withBookmarksSupplier(callable $bookmarksSupplier): self
    {
        return new self($this->initialBookmarks, $this->bookmarksConsumer, $bookmarksSupplier);
    }

    /**
     * @return list<Bookmark>
     */
    public function getInitialBookmarks(): array
    {
        return $this->initialBookmarks;
    }

    /**
     * @return callable(list<Bookmark>): void|null
     */
    public function getBookmarksConsumer(): ?callable
    {
        return $this->bookmarksConsumer;
    }

    /**
     * @return callable(): list<Bookmark>|null
     */
    public function getBookmarksSupplier(): ?callable
    {
        return $this->bookmarksSupplier;
    }
}
