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

use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\BookmarkHolder;
use Symfony\Component\Uid\Uuid;

final class BookmarkManagerState
{
    public function __construct(
        public readonly Uuid $id,
        public readonly BookmarkHolder $holder,
        public readonly bool $supplierRegistered,
        public readonly bool $consumerRegistered,
    ) {
    }

    /**
     * @param list<string>|null $initialBookmarks
     */
    public static function create(Uuid $id, ?array $initialBookmarks, bool $supplierRegistered, bool $consumerRegistered): self
    {
        $bookmark = new Bookmark($initialBookmarks ?? []);

        return new self($id, new BookmarkHolder($bookmark), $supplierRegistered, $consumerRegistered);
    }
}
