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

use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\SessionLastBookmarksRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BookmarksResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @implements AbstractRunner<SessionLastBookmarksRequest>
 */
final class SessionLastBookmarks implements RequestHandlerInterface
{
    public function __construct(
        private readonly MainRepository $repository,
    ) {
    }

    /**
     * @param SessionLastBookmarksRequest $request
     *
     * @return TestkitResponseInterface<BookmarksResponse>
     */
    public function handle($request): TestkitResponseInterface
    {
        $session = $this->repository->getSession($request->getSessionId());

        $bookmarks = $session->getLastBookmark()->values();

        return new BookmarksResponse($bookmarks);
    }

    protected function getId($request): Uuid
    {
        return $request->getSessionId();
    }
}
