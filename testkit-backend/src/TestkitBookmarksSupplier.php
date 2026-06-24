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
use Laudis\Neo4j\TestkitBackend\Requests\BookmarksSupplierCompletedRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BookmarksSupplierRequestResponse;
use RuntimeException;

final class TestkitBookmarksSupplier
{
    public function __construct(
        private readonly string $bookmarkManagerId,
        private readonly TestkitCallbackDispatcher $dispatcher,
        private readonly IdGenerator $idGenerator,
    ) {
    }

    /**
     * @return list<Bookmark>
     */
    public function get(): array
    {
        $callbackId = $this->idGenerator->newId();
        $result = $this->dispatcher->dispatch(
            new BookmarksSupplierRequestResponse($callbackId, $this->bookmarkManagerId),
        );

        if (!$result instanceof BookmarksSupplierCompletedRequest) {
            throw new RuntimeException('Expected BookmarksSupplierCompleted but got '.get_debug_type($result));
        }

        return array_map(static fn (string $value): Bookmark => new Bookmark([$value]), $result->getBookmarks());
    }
}
