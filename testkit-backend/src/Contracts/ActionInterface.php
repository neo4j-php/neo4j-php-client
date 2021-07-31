<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Contracts;

/**
 * @template T
 */
interface ActionInterface
{
    /**
     * @param T $input
     */
    public function handle($input): TestkitResponseInterface;
}
