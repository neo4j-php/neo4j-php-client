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

namespace Laudis\Neo4j\TestkitBackend\Handlers;

use Laudis\Neo4j\TestkitBackend\BookmarkManagerState;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\NewBookmarkManagerRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BookmarkManagerResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @implements RequestHandlerInterface<NewBookmarkManagerRequest>
 */
final class NewBookmarkManager implements RequestHandlerInterface
{
    public function __construct(
        private MainRepository $repository,
    ) {
    }

    /**
     * @param NewBookmarkManagerRequest $request
     */
    public function handle($request): BookmarkManagerResponse
    {
        $id = Uuid::v4();
        $state = BookmarkManagerState::create(
            $id,
            $request->initialBookmarks,
            $request->bookmarksSupplierRegistered,
            $request->bookmarksConsumerRegistered
        );
        $this->repository->addBookmarkManager($id, $state);

        return new BookmarkManagerResponse($id);
    }
}
