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
use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\ResultListRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RecordListResponse;
use Throwable;

/**
 * Materializes the full result (PULL / iterate) for TestKit Result.list().
 *
 * @implements RequestHandlerInterface<ResultListRequest>
 */
final class ResultList implements RequestHandlerInterface
{
    public function __construct(
        private readonly MainRepository $repository,
    ) {
    }

    /**
     * @param ResultListRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        try {
            $result = $this->repository->getRecords($request->getResultId());
            if ($result instanceof TestkitResponseInterface) {
                return $result;
            }

            $rows = [];
            if ($result instanceof SummarizedResult) {
                $this->repository->drainPendingIteratorNexts($request->getResultId(), $result);
                $iterable = $result->list();
            } else {
                $iterable = $result;
            }
            foreach ($iterable as $row) {
                $r = [];
                foreach ($row as $value) {
                    $r[] = $value;
                }
                $rows[] = $r;
            }

            $this->repository->removeRecords($request->getResultId());

            return new RecordListResponse($rows);
        } catch (Neo4jException $e) {
            $response = new DriverErrorResponse($request->getResultId(), $e);
            // Keep error for RetryableNegative lookup by result id (execute_read tx_func tests).
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
            || str_contains($message, 'broken');
    }
}
