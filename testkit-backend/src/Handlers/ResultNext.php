<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\TestkitBackend\Handlers;

use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\ResultNextRequest;
use Laudis\Neo4j\TestkitBackend\Responses\NullRecordResponse;
use Laudis\Neo4j\TestkitBackend\Responses\RecordResponse;
use Laudis\Neo4j\TestkitBackend\Responses\Types\CypherObject;

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
        $record = $this->repository->getRecords($request->getResultId());
        if ($record instanceof TestkitResponseInterface) {
            return $record;
        }

        $iterator = $this->repository->getIterator($request->getResultId());

        if (!$iterator->valid()) {
            return new NullRecordResponse();
        }

        $current = $iterator->current();

        $iterator->next();

        $values = [];
        foreach ($current as $value) {
            $values[] = CypherObject::autoDetect($value);
        }

        return new RecordResponse($values);
    }
}
