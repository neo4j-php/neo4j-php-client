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

use Bolt\error\BoltException;
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

    public function handle($request): TestkitResponseInterface
    {
        try {
            $record = $this->repository->getRecords($request->getResultId());
            if ($record instanceof TestkitResponseInterface) {
                return $record;
            }

            $count = $record->count();
            if ($count !== 1) {
                $err = new Neo4jException([Neo4jError::fromMessageAndCode(
                    'Neo.ClientError.Statement.ResultNotSingle',
                    sprintf('Expected exactly one result row, found %d.', $count)
                )]);
                $response = new DriverErrorResponse($request->getResultId(), $err);
                $this->repository->addRecords($request->getResultId(), $response);

                return $response;
            }

            $values = [];
            foreach ($record->getAsCypherMap(0) as $value) {
                $values[] = CypherObject::autoDetect($value);
            }

            $this->repository->removeRecords($request->getResultId());

            return new RecordResponse($values);
        } catch (Neo4jException $e) {
            $response = new DriverErrorResponse($request->getResultId(), $e);
            $this->repository->addRecords($request->getResultId(), $response);

            return $response;
        } catch (BoltException $e) {
            $neo4jError = Neo4jError::fromMessageAndCode('Neo.ClientError.General.ConnectionError', $e->getMessage());
            $wrapped = new Neo4jException([$neo4jError], $e);
            $response = new DriverErrorResponse($request->getResultId(), $wrapped);
            $this->repository->addRecords($request->getResultId(), $response);

            return $response;
        } catch (Throwable $e) {
            if ($this->isConnectionOrSocketError($e)) {
                $neo4jError = Neo4jError::fromMessageAndCode('Neo.ClientError.General.ConnectionError', $e->getMessage());
                $wrapped = new Neo4jException([$neo4jError], $e);
                $response = new DriverErrorResponse($request->getResultId(), $wrapped);
                $this->repository->addRecords($request->getResultId(), $response);

                return $response;
            }
            throw $e;
        }
    }

    private function isConnectionOrSocketError(Throwable $e): bool
    {
        $message = strtolower($e->getMessage());

        return str_contains($message, 'broken pipe')
            || str_contains($message, 'connection reset')
            || str_contains($message, 'connection refused')
            || str_contains($message, 'connection closed')
            || str_contains($message, 'connection is closed')
            || str_contains($message, 'interrupted system call')
            || str_contains($message, 'i/o error')
            || str_contains($message, 'network read incomplete')
            || str_contains($message, 'network write incomplete')
            || str_contains($message, 'socket')
            || str_contains($message, 'broken')
            || str_contains($message, 'already been closed');
    }
}
