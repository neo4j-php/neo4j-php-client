<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Requests;


use Symfony\Component\Uid\Uuid;

final class VerifyConnectivityRequest
{
    private Uuid $driverId;

    public function __construct(Uuid $driverId)
    {
        $this->driverId = $driverId;
    }

    public function getDriverId(): Uuid
    {
        return $this->driverId;
    }
}
