<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Actions;


use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;

final class GetFeatures implements ActionInterface
{
    public function handle(array $data): array
    {
        return ['name' => 'FeatureList', 'data' => ['features' => []]];
    }
}
