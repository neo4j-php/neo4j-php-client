<?php

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Contracts;

use Laudis\Neo4j\Databags\ConnectionRequestData;
use Laudis\Neo4j\Databags\SessionConfiguration;

/**
 * @template T
 */
interface ConnectionFactoryInterface
{
    /**
     * @return ConnectionInterface<T>
     */
    public function createConnection(ConnectionRequestData $data, SessionConfiguration $sessionConfig): ConnectionInterface;

    /**
     * @param ConnectionInterface<T> $connection
     */
    public function canReuseConnection(ConnectionInterface $connection, ConnectionRequestData $data): bool;

    /**
     * @param ConnectionInterface<T> $connection
     *
     * @return ConnectionInterface<T>
     */
    public function reuseConnection(ConnectionInterface $connection, SessionConfiguration $sessionConfig): ConnectionInterface;
}
