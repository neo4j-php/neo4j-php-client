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

use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\ResultSingleRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RecordResponse;
use Laudis\Neo4j\TestkitBackend\Responses\Types\CypherObject;

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
    public function __construct(
        private readonly MainRepository $repository,
    ) {
    }

    public function handle($request): TestkitResponseInterface
    {
        $record = $this->repository->getRecords($request->getResultId());
        if ($record instanceof TestkitResponseInterface) {
            $err = new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Statement.ResultNotSingle', 'Something went wrong with the result handling')]);

            return new DriverErrorResponse($request->getResultId(), $err);
        }

        $count = $record->count();
        if ($count !== 1) {
            $err = new Neo4jException([Neo4jError::fromMessageAndCode(
                'Neo.ClientError.Statement.ResultNotSingle',
                sprintf('Expected exactly one result row, found %d.', $count)
            )]);

            return new DriverErrorResponse($request->getResultId(), $err);
        }

        $values = [];
        foreach ($record->getAsCypherMap(0) as $value) {
            $values[] = CypherObject::autoDetect($value);
        }

        return new RecordResponse($values);
    }
}
