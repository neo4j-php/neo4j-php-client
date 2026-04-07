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
use Laudis\Neo4j\TestkitBackend\Requests\ResultPeekRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\NullRecordResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RecordResponse;
use Laudis\Neo4j\TestkitBackend\Responses\Types\CypherObject;
use Throwable;

/**
 * Peek at the next record without advancing the iterator position used by ResultNext.
 *
 * @implements RequestHandlerInterface<ResultPeekRequest>
 */
final class ResultPeek implements RequestHandlerInterface
{
    public function __construct(
        private readonly MainRepository $repository,
    ) {
    }

    /**
     * @param ResultPeekRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        try {
            $record = $this->repository->getRecords($request->getResultId());
            if ($record instanceof TestkitResponseInterface) {
                return $record;
            }

            $iterator = $this->repository->getIterator($request->getResultId());

            if (!$iterator->valid()) {
                return new NullRecordResponse();
            }

            $current = $iterator->current();

            $values = [];
            foreach ($current as $value) {
                $values[] = CypherObject::autoDetect($value);
            }

            return new RecordResponse($values);
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
