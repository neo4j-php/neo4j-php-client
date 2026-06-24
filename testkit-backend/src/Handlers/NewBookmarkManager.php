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

use Laudis\Neo4j\BookmarkManagers;
use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\BookmarkManagerConfig;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\IdGenerator;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\NewBookmarkManagerRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BookmarkManagerResponse;
use Laudis\Neo4j\TestkitBackend\TestkitBookmarksConsumer;
use Laudis\Neo4j\TestkitBackend\TestkitBookmarksSupplier;
use Laudis\Neo4j\TestkitBackend\TestkitCallbackDispatcher;

/**
 * @implements RequestHandlerInterface<NewBookmarkManagerRequest>
 */
final class NewBookmarkManager implements RequestHandlerInterface
{
    public function __construct(
        private readonly MainRepository $repository,
        private readonly IdGenerator $idGenerator,
        private readonly TestkitCallbackDispatcher $callbackDispatcher,
    ) {
    }

    /**
     * @param NewBookmarkManagerRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $id = $this->idGenerator->newId();
        $initialBookmarks = array_map(
            static fn (string $value): Bookmark => new Bookmark([$value]),
            $request->initialBookmarks ?? [],
        );

        $config = BookmarkManagerConfig::default()->withInitialBookmarks($initialBookmarks);

        if ($request->bookmarksSupplierRegistered) {
            $supplier = new TestkitBookmarksSupplier($id, $this->callbackDispatcher, $this->idGenerator);
            $config = $config->withBookmarksSupplier(static fn (): array => $supplier->get());
        }

        if ($request->bookmarksConsumerRegistered) {
            $consumer = new TestkitBookmarksConsumer($id, $this->callbackDispatcher, $this->idGenerator);
            $config = $config->withBookmarksConsumer(static fn (array $bookmarks): mixed => $consumer->accept($bookmarks));
        }

        $manager = BookmarkManagers::defaultManager($config);
        $this->repository->addBookmarkManager($id, $manager);

        return new BookmarkManagerResponse($id);
    }
}
