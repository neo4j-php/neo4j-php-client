<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Handlers;


use Ds\Map;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\DriverCloseRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverResponse;

/**
 * @implements RequestHandlerInterface<DriverCloseRequest>
 */
final class DriverClose implements RequestHandlerInterface
{
    public function __construct(private MainRepository $repository)
    {
    }

    /**
     * @param DriverCloseRequest $request
     */
    public function handle($request): DriverResponse
    {
        $this->repository->removeDriver($request->getDriverId());

        return new DriverResponse($request->getDriverId());
    }
}
