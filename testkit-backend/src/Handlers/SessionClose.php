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

use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\SessionCloseRequest;
use Laudis\Neo4j\TestkitBackend\Responses\SessionResponse;

/**
 * @implements RequestHandlerInterface<SessionCloseRequest>
 */
final class SessionClose implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param SessionCloseRequest $request
     */
    public function handle($request): SessionResponse
    {
        $this->repository->removeSession($request->getSessionId());

        return new SessionResponse($request->getSessionId());
    }
}
