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
use Laudis\Neo4j\TestkitBackend\Requests\ResultPeekRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverErrorResponse;
use Laudis\Neo4j\TestkitBackend\Responses\NullRecordResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RecordResponse;
use Laudis\Neo4j\TestkitBackend\Responses\Types\CypherObject;
use Throwable;

/**
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

            // Prime by fetching current - triggers setupCache -> getGenerator()->rewind() -> PULL.
            // valid() alone can be false for unprimed CypherList; current() forces the generator to run.
            try {
                $current = $iterator->current();
            } catch (Throwable) {
                return new NullRecordResponse();
            }
            if (!$iterator->valid()) {
                return new NullRecordResponse();
            }
            $values = [];
            foreach ($current as $value) {
                $values[] = CypherObject::autoDetect($value);
            }

            return new RecordResponse($values);
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
