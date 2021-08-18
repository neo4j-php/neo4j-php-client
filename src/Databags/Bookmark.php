<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

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
