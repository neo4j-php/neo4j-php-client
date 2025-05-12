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
use Laudis\Neo4j\TestkitBackend\Requests\SessionWriteTransactionRequest;
use Laudis\Neo4j\TestkitBackend\Responses\RetryableTryResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @implements RequestHandlerInterface<SessionWriteTransactionRequest>
 */
final class SessionWriteTransaction implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param SessionWriteTransactionRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $session = $this->repository->getSession($request->getSessionId());

        $id = Uuid::v4();

        $this->repository->addTransaction($id, $session);
        $this->repository->bindTransactionToSession($request->getSessionId(), $id);

        return new RetryableTryResponse($id);
    }
}
