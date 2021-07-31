<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Contracts;


interface ActionInterface
{
    public function handle(array $data): TestkitResponseInterface;
}
