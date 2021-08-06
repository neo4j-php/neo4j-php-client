<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Handlers;


use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\ResultConsumeRequest;
use Laudis\Neo4j\TestkitBackend\Responses\BackendErrorResponse;

/**
 * @implements RequestHandlerInterface<ResultConsumeRequest>
 */
final class ResultConsume implements RequestHandlerInterface
{
    private MainRepository $repository;

    public function __construct(MainRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @param ResultConsumeRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $result = $this->repository->getRecords($request->getResultId());
        if ($result->current() instanceof TestkitResponseInterface) {
            return $result->current();
        }

        return new BackendErrorResponse('Result consumption not implemented yet'); // TODO
    }
}
