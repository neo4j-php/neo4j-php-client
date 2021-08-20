<?php
declare(strict_types=1);


namespace Laudis\Neo4j\TestkitBackend\Contracts;

/**
 * @template T
 */
interface RequestHandlerInterface
{
    /**
     * @param T $request
     */
    public function handle($request): TestkitResponseInterface;
}
