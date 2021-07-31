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

use Bolt\error\MessageException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\SessionRunRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\ResultResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @implements RequestHandlerInterface<SessionRunRequest>
 */
final class SessionRun implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param SessionRunRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $session = $this->repository->getSession($request->getSessionId());
        try {
            $result = $session->run($request->getCypher(), $request->getParams());
        } catch (MessageException $exception) {
            return new DriverErrorResponse(
                $request->getSessionId(),
                'todo',
                $exception->getMessage(),
                'todo'
            );
        }
        $id = Uuid::v4();
        $this->repository->addRecords($id, $result->getIterator());

        return new ResultResponse($id, $result->isEmpty() ? [] : $result->first()->keys());
    }
}
