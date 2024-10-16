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

use Generator;
use Laudis\Neo4j\Databags\SessionConfiguration;

/**
 * A connection pool acts as a connection factory by managing multiple connections.
 *
 * @template Connection of ConnectionInterface
 */
interface ConnectionPoolInterface
{
    /**
     * Acquires a connection from the pool.
     *
     * A key will be the amount of times you have fetched the value of the generator.
     * The value will be the time in seconds that has passed since requesting the connection.
     * You can abort the process of acquiring a connection by sending false to the generator.
     * The returned value will be the actual connection.
     *
     * @return Generator<
     *      int,
     *      float,
     *      bool,
     *      Connection|null
     * >
     */
    public function acquire(SessionConfiguration $config): Generator;

    /**
     * Releases a connection back to the pool.
     */
    public function release(ConnectionInterface $connection): void;

    /**
     * Closes all connections in the pool.
     */
    public function close(): void;
}
