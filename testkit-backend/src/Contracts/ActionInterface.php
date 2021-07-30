<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Contracts;


interface ActionInterface
{
    /**
     * @return mixed
     */
    public function handle(array $parameters);
}
