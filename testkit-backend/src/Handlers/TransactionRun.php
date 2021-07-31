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
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\TransactionRunRequest;
use Laudis\Neo4j\TestkitBackend\Responses\ResultResponse;
use Symfony\Component\Uid\Uuid;

/**
 * @implements RequestHandlerInterface<TransactionRunRequest>
 */
final class TransactionRun implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param TransactionRunRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $tsx = $this->repository->getTransaction($request->getTxId());

        $results = $tsx->run($request->getCypher(), $request->getParams());

        $id = Uuid::v4();
        $this->repository->addRecords($id, $results->getIterator());

        return new ResultResponse($id, $results->isEmpty() ? [] : $results->first()->keys());
    }
}
