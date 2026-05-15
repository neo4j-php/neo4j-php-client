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

namespace Laudis\Neo4j\TestkitBackend;

use Laudis\Neo4j\Contracts\Neo4jBookmarkManagerHooksInterface;

final class TestkitBookmarkManagerHooks implements Neo4jBookmarkManagerHooksInterface
{
    public function __construct(
        private readonly ClientProtocolBridge $bridge,
        private readonly BookmarkManagerState $state,
    ) {
    }

    public function getSupplierBookmarks(): array
    {
        if (!$this->state->supplierRegistered) {
            return [];
        }

        return $this->bridge->requestSupplierBookmarks($this->state->id);
    }

    public function notifyBookmarksUpdated(array $bookmarks): void
    {
        if (!$this->state->consumerRegistered || $bookmarks === []) {
            return;
        }

        $this->bridge->requestBookmarksConsumer($this->state->id, $bookmarks);
    }
}
