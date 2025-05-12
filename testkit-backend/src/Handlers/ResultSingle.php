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
use Laudis\Neo4j\TestkitBackend\Requests\ResultSingleRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BackendErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RecordResponse;

/**
 * Request to expect and return exactly one record in the result stream.
 *
 * Backend should respond with a Record if exactly one record was found.
 * If more or fewer records are left in the result stream, or if any other
 * error occurs while retrieving the records, an Error response should be
 * returned.
 *
 * @implements RequestHandlerInterface<ResultSingleRequest>
 */
final class ResultSingle implements RequestHandlerInterface
{
    private function __construct(
        private readonly MainRepository $repository,
    ) {
    }

    public function handle($request): TestkitResponseInterface
    {
        $record = $this->repository->getRecords($request->getResultId());
        if ($record instanceof TestkitResponseInterface) {
            return new BackendErrorResponse('Something went wrong with the result handling');
        }

        $count = $record->count();
        if ($count !== 1) {
            return new BackendErrorResponse(sprintf('Found exactly %s result rows, but expected just one.', $count));
        }

        $values = [];
        foreach ($record->getAsCypherMap(0) as $value) {
            $values[] = $value;
        }

        return new RecordResponse($values);
    }
}
