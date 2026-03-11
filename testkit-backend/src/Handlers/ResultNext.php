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
        try {
            $record = $this->repository->getRecords($request->getResultId());
            if ($record instanceof TestkitResponseInterface) {
                return $record;
            }

            $iterator = $this->repository->getIterator($request->getResultId());

            // Advance first - this primes the iterator and triggers PULL for fresh results.
            // Must run before valid() because CypherList's valid() can return false for an
            // unprimed generator (before any next()), causing NullRecord without ever pulling.
            $iterator->next();

            if (!$iterator->valid()) {
                return new NullRecordResponse();
            }

            // Get the current record
            $current = $iterator->current();

            // Advance so list() and subsequent next() see the correct position
            $iterator->next();

            $values = [];
            foreach ($current as $value) {
                $values[] = CypherObject::autoDetect($value);
            }

            return new RecordResponse($values);
        } catch (Neo4jException $e) {
            $this->repository->removeRecords($request->getResultId());

            return new DriverErrorResponse($request->getResultId(), $e);
        } catch (Throwable $e) {
            // Convert any other throwable (including unhandled exceptions) to Neo4jException format
            $this->repository->removeRecords($request->getResultId());
            $neo4jError = Neo4jError::fromMessageAndCode('Neo.ClientError.General.UnknownError', $e->getMessage());
            $neo4jException = new Neo4jException([$neo4jError], $e);

            return new DriverErrorResponse($request->getResultId(), $neo4jException);
        }
    }
}
