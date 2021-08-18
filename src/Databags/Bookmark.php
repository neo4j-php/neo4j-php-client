<?php
declare(strict_types=1);

namespace Laudis\Neo4j\Databags;

use Ds\Set;
use Symfony\Component\Uid\Uuid;

final class Bookmark
{
    private Set $bookmarks;

    /**
     * @param Set<string> $bookmarks
     */
    public function __construct(?Set $bookmarks = null)
    {
        $this->bookmarks = $bookmarks ?? new Set();
    }

    public function isEmpty(): bool
    {
        return $this->bookmarks->isEmpty();
    }

    /**
     * @return Set<string>
     */
    public function values(): Set
    {
        return $this->bookmarks;
    }

    public function withIncrement(?string $bookmark = null): self
    {
        $copy = $this->bookmarks->copy();
        $copy->add($bookmark ?? Uuid::v4()->toRfc4122());
        return new self($copy);
    }
}
