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
        $session = $this->repository->getSession($request->getSessionId());

        if ($session !== null) {
            $session->close();
        }
        $this->repository->removeSession($request->getSessionId());

        return new SessionResponse($request->getSessionId());
    }
}
