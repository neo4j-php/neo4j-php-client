<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Actions;


final class GetFeatures
{
    public function handle(): array
    {
        return ['name' => 'FeatureList', 'data' => ['features' => []]];
    }
}
