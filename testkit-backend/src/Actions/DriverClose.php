<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Actions;


use Ds\Map;
use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;

final class DriverClose implements ActionInterface
{
    private Map $drivers;

    public function __construct(Map $drivers)
    {
        $this->drivers = $drivers;
    }

    public function handle(array $data): array
    {
        $this->drivers->remove($data['driverId']);

        return [
            'name' => 'Driver',
            'data' => [
                'id' => $data['driverId'],
            ],
        ];
    }
}
