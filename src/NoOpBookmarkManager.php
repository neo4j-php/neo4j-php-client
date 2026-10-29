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

use Laudis\Neo4j\Contracts\BookmarkManagerInterface;

/**
 * A no-op {@see BookmarkManagerInterface} implementation.
 */
final class NoOpBookmarkManager implements BookmarkManagerInterface
{
    private static ?self $instance = null;

    private function __construct()
    {
    }

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function updateBookmarks(array $previousBookmarks, array $newBookmarks): void
    {
    }

    public function getBookmarks(): array
    {
        return [];
    }
}
