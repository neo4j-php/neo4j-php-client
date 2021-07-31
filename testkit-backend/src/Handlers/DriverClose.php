<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Handlers;


use Ds\Map;
use Laudis\Neo4j\TestkitBackend\Contracts\RequestHandlerInterface;
use Laudis\Neo4j\TestkitBackend\Requests\DriverCloseRequest;
use Laudis\Neo4j\TestkitBackend\Responses\DriverResponse;

/**
 * @implements RequestHandlerInterface<DriverCloseRequest>
 */
final class DriverClose implements RequestHandlerInterface
{
    private Map $drivers;

    public function __construct(Map $drivers)
    {
        $this->drivers = $drivers;
    }

    /**
     * @param DriverCloseRequest $request
     */
    public function handle($request): DriverResponse
    {
        $this->drivers->remove($request->getDriverId()->toRfc4122());

        return new DriverResponse($request->getDriverId());
    }
}
