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

use Laudis\Neo4j\Databags\Bookmark;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\NewSessionRequest;
use Laudis\Neo4j\TestkitBackend\Responses\SessionResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @implements RequestHandlerInterface<NewSessionRequest>
 */
final class NewSession implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param NewSessionRequest $request
     */
    public function handle($request): SessionResponse
    {
        $driver = $this->repository->getDriver($request->driverId);

        $config = SessionConfiguration::default()
            ->withAccessMode($request->accessMode === 'r' ? AccessMode::READ() : AccessMode::WRITE());

        if ($request->bookmarks !== null) {
            $config = $config->withBookmarks([new Bookmark($request->bookmarks)]);
        }

        if ($request->database !== null) {
            $config = $config->withDatabase($request->database);
        }

        $config = $config->withFetchSize($request->fetchSize ?? 1);

        $session = $driver->createSession($config);
        $id = Uuid::v4();
        $this->repository->addSession($id, $session);

        return new SessionResponse($id);
    }
}
