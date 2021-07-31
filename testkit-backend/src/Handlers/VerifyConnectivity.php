<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Handlers;


use Ds\Map;
use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Laudis\Neo4j\TestkitBackend\Requests\VerifyConnectivityRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverResponse;

/**
 * @implements ActionInterface<VerifyConnectivityRequest>
 */
final class VerifyConnectivity implements ActionInterface
{
    private Map $drivers;

    public function __construct(Map $drivers)
    {
        $this->drivers = $drivers;
    }

    /**
     * @param VerifyConnectivityRequest $request
     */
    public function handle($request): TestkitResponseInterface
    {
        $driver = $this->drivers->get($request->getDriverId()->toRfc4122());

        $driver->acquireSession()->run('RETURN 2 as x');

        return new DriverResponse($request->getDriverId());
    }
}
