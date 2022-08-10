<?php

declare(strict_types=1);

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Contracts;

use Laudis\Neo4j\Databags\SessionConfiguration;

/**
 * A connection pool acts as a connection factory by managing multiple connections.
 *
 * @template ProtocolImplementation The implementation of the protocol used in the connection.
 */
interface ConnectionPoolInterface
{
    /**
     * Acquires a connection from the pool.
     *
     * @return ConnectionInterface<ProtocolImplementation>
     */
    public function acquire(SessionConfiguration $config): ConnectionInterface;

    /**
     * Releases a connection back to the pool.
     */
    public function release(ConnectionInterface $connection): void;
}
