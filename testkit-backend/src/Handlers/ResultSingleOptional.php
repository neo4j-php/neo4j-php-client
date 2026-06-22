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
use Laudis\Neo4j\TestkitBackend\Requests\ResultSingleOptionalRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RecordOptionalResponse;
use Throwable;

/**
 * @implements RequestHandlerInterface<ResultSingleOptionalRequest>
 */
final class ResultSingleOptional implements RequestHandlerInterface
{
    public function __construct(
        private readonly MainRepository $repository,
    ) {
    }

    /**
     * @param ResultSingleOptionalRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        try {
            $result = $this->repository->getRecords($request->getResultId());
            if ($result instanceof TestkitResponseInterface) {
                return $result;
            }

            $rows = [];
            foreach ($result as $row) {
                $r = [];
                foreach ($row as $value) {
                    $r[] = $value;
                }
                $rows[] = $r;
            }

            $this->repository->removeRecords($request->getResultId());

            $n = count($rows);
            if ($n === 0) {
                return new RecordOptionalResponse(null, []);
            }
            if ($n === 1) {
                return new RecordOptionalResponse($rows[0], []);
            }

            return new RecordOptionalResponse(
                $rows[0],
                ['Expected a single record but found multiple records in the stream.']
            );
        } catch (Neo4jException $e) {
            $this->repository->removeRecords($request->getResultId());

            return new DriverErrorResponse($request->getResultId(), $e);
        } catch (BoltException $e) {
            $this->repository->removeRecords($request->getResultId());
            $neo4jError = Neo4jError::fromMessageAndCode('Neo.ClientError.General.ConnectionError', $e->getMessage());
            $wrapped = new Neo4jException([$neo4jError], $e);

            return new DriverErrorResponse($request->getResultId(), $wrapped);
        } catch (Throwable $e) {
            $this->repository->removeRecords($request->getResultId());
            if ($this->isConnectionOrSocketError($e)) {
                $neo4jError = Neo4jError::fromMessageAndCode('Neo.ClientError.General.ConnectionError', $e->getMessage());
                $wrapped = new Neo4jException([$neo4jError], $e);

                return new DriverErrorResponse($request->getResultId(), $wrapped);
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
            || str_contains($message, 'broken');
    }
}
