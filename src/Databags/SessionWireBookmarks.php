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
 * Resolves the bookmark set sent on the wire, including optional Neo4j bookmark-manager supplier callbacks.
 *
 * Supplier hooks may perform I/O; that work is kept off SessionConfiguration so it can stay Psalm-immutable.
 */
final class SessionWireBookmarks
{
    public static function resolve(SessionConfiguration $sessionBookmarks): Bookmark
    {
        $merged = $sessionBookmarks->getSessionBookmarksForWire();
        $hooks = $sessionBookmarks->getBookmarkManagerHooks();
        if ($hooks === null) {
            return $merged;
        }

        $extra = $hooks->getSupplierBookmarks();

        return Bookmark::from([$merged, new Bookmark($extra)]);
    }
}
