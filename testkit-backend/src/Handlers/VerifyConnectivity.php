<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Handlers;


use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\MainRepository;
use Laudis\Neo4j\TestkitBackend\Requests\VerifyConnectivityRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverResponse;

/**
 * @implements RequestHandlerInterface<VerifyConnectivityRequest>
 */
final class VerifyConnectivity implements RequestHandlerInterface
{
    public function __construct(private MainRepository $repository)
    {
    }

    /**
     * @param VerifyConnectivityRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $driver = $this->repository->getDriver($request->getDriverId());

        $driver->createSession()->run('RETURN 2 as x');

        return new DriverResponse($request->getDriverId());
    }
}
