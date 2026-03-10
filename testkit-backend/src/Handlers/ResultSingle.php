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
use Throwable;

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

    /**
     * @param ResultSingleRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        try {
            $record = $this->repository->getRecords($request->getResultId());
            if ($record instanceof TestkitResponseInterface) {
                return $record;
            }

            $iterator = $this->repository->getIterator($request->getResultId());
            $records = [];

            // Iterate to consume result (triggers PULL) and collect records
            foreach ($iterator as $current) {
                $values = [];
                foreach ($current as $value) {
                    $values[] = CypherObject::autoDetect($value);
                }
                $records[] = $values;
            }

            if (count($records) !== 1) {
                $neo4jError = Neo4jError::fromMessageAndCode(
                    'Neo.ClientError.Statement.ResultNotSingle',
                    sprintf('Expected a result with exactly one record, but found %d', count($records))
                );

                return new DriverErrorResponse($request->getResultId(), new Neo4jException([$neo4jError]));
            }

            return new RecordResponse($records[0]);
        } catch (Neo4jException $e) {
            $this->repository->removeRecords($request->getResultId());

            return new DriverErrorResponse($request->getResultId(), $e);
        } catch (Throwable $e) {
            $this->repository->removeRecords($request->getResultId());
            $neo4jError = Neo4jError::fromMessageAndCode('Neo.ClientError.General.UnknownError', $e->getMessage());
            $neo4jException = new Neo4jException([$neo4jError], $e);

            return new DriverErrorResponse($request->getResultId(), $neo4jException);
        }
    }
}
