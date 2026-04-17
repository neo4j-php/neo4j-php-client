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
use Laudis\Neo4j\TestkitBackend\Requests\ResultNextRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\NullRecordResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RecordResponse;
use Laudis\Neo4j\TestkitBackend\Responses\Types\CypherObject;
use Throwable;

/**
 * @implements RequestHandlerInterface<ResultNextRequest>
 */
final class ResultNext implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param ResultNextRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $iterator = null;
        try {
            $record = $this->repository->getRecords($request->getResultId());
            if ($record instanceof TestkitResponseInterface) {
                return $record;
            }

            $iterator = $this->repository->getIterator($request->getResultId());
            // Defer Iterator::next() until here so the Bolt stream is not advanced (e.g. second PULL)
            // until the client asks for the next record — required for disconnect stubs and Result.list().
            $this->repository->drainPendingIteratorNexts($request->getResultId(), $iterator);

            // Check if iterator is valid - this may trigger generator to start and fetch results
            // If the connection is closed, this will throw an exception which we catch below
            if (!$iterator->valid()) {
                return new NullRecordResponse();
            }

            // Get the current record
            $current = $iterator->current();
            $this->repository->setIteratorFetchedFirst($request->getResultId(), true);

            $values = [];
            foreach ($current as $value) {
                $values[] = CypherObject::autoDetect($value);
            }

            $this->repository->addPendingIteratorNext($request->getResultId());
            $this->repository->clearPendingDriverError($request->getResultId());

            return new RecordResponse($values);
        } catch (Neo4jException $e) {
            $this->repository->setPendingDriverError($request->getResultId(), $e);
            if ($iterator !== null) {
                try {
                    if ($iterator->valid()) {
                        $iterator->next();
                    }
                } catch (Throwable) {
                    // Iterator may be exhausted or the stream broken after a row-level decode error.
                }
            }

            return new DriverErrorResponse($request->getResultId(), $e);
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
