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
use Laudis\Neo4j\TestkitBackend\Requests\ResultConsumeRequest;
use Laudis\Neo4j\TestkitBackend\Responses\SummaryResponse;

/**
 * @implements RequestHandlerInterface<ResultConsumeRequest>
 */
final class ResultConsume implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param ResultConsumeRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $result = $this->repository->getRecords($request->getResultId());

        if ($result instanceof TestkitResponseInterface) {
            return $result;
        }

        return new SummaryResponse($result);
    }
}
