<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Actions;


use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;

final class StartTest implements ActionInterface
{
    public function handle(array $parameters)
    {
        return 'SkipTest';
    }
}
