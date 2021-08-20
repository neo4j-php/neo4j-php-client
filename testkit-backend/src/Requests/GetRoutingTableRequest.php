<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Requests;


use Symfony\Component\Uid\Uuid;

final class GetRoutingTableRequest
{
    private Uuid $driverId;
    private ?string $database;

    public function __construct(Uuid $driverId, ?string $database)
    {
        $this->driverId = $driverId;
        $this->database = $database;
    }

    public function getDriverId(): Uuid
    {
        return $this->driverId;
    }

    public function getDatabase(): ?string
    {
        return $this->database;
    }
}
