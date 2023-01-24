<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Requests;


use Symfony\Component\Uid\Uuid;

final class DriverCloseRequest
{
    public function __construct(private Uuid $driverId)
    {
    }

    public function getDriverId(): Uuid
    {
        return $this->driverId;
    }
}
