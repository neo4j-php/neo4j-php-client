<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Input;


use Symfony\Component\Uid\Uuid;

final class CheckMultiDBSupportInput
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
