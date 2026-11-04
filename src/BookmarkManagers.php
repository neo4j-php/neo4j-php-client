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
use Laudis\Neo4j\Databags\BookmarkManagerConfig;

/**
 * Creates new instances of {@see BookmarkManagerInterface}.
 */
final class BookmarkManagers
{
    private function __construct()
    {
    }

    public static function defaultManager(BookmarkManagerConfig $config): BookmarkManagerInterface
    {
        return new Neo4jBookmarkManager(
            $config->getInitialBookmarks(),
            $config->getBookmarksConsumer(),
            $config->getBookmarksSupplier(),
        );
    }
}
