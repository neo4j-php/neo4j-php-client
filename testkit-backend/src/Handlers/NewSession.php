<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\TestkitBackend\Handlers;

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
        $driver = $this->repository->getDriver($request->getDriverId());

        $config = SessionConfiguration::default()
            ->withAccessMode($request->getAccessMode() === 'r' ? AccessMode::READ() : AccessMode::WRITE());

        if ($request->getBookmarks() !== null) {
            $config = $config->withBookmarks($request->getBookmarks());
        }

        if ($request->getDatabase() !== null) {
            $config = $config->withDatabase($request->getDatabase());
        }

        if ($request->getFetchSize() !== null) {
            $config = $config->withFetchSize($request->getFetchSize());
        }

        $session = $driver->createSession($config);
        $id = Uuid::v4();
        $this->repository->addSession($id, $session);

        return new SessionResponse($id);
    }
}
