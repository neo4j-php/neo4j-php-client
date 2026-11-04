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
use Laudis\Neo4j\TestkitBackend\Responses\BookmarksConsumerRequestResponse;

final class TestkitBookmarksConsumer
{
    public function __construct(
        private readonly string $bookmarkManagerId,
        private readonly TestkitCallbackDispatcher $dispatcher,
        private readonly IdGenerator $idGenerator,
    ) {
    }

    /**
     * @param list<Bookmark> $bookmarks
     */
    public function accept(array $bookmarks): void
    {
        $callbackId = $this->idGenerator->newId();
        $values = Bookmark::from($bookmarks)->values();

        $this->dispatcher->dispatch(
            new BookmarksConsumerRequestResponse($callbackId, $this->bookmarkManagerId, $values),
        );
    }
}
