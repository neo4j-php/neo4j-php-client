<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Responses;

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Represents the driver instance in the backend.
 */
final class DriverResponse implements TestkitResponseInterface
{
    private Uuid $id;

    public function __construct(Uuid $id)
    {
        $this->id = $id;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'Driver',
            'data' => [
                'id' => $this->id->toRfc4122(),
            ],
        ];
    }
}
