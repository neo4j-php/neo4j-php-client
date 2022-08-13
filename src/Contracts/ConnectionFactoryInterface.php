<?php

namespace Laudis\Neo4j\Contracts;

/**
 * @template T
 */
interface ConnectionFactoryInterface
{
    /**
     * @return ConnectionInterface<T>
     */
    public function createConnection(AuthenticateInterface $auth): ConnectionInterface;
}
