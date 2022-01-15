<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Contracts;

use Laudis\Neo4j\Databags\SessionConfiguration;
use Psr\Http\Message\UriInterface;

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
    public function acquire(
        UriInterface $uri,
        AuthenticateInterface $authenticate,
        SessionConfiguration $config
    ): ConnectionInterface;

    /**
     * Returns true if the connection pool can make a connection to the server with the current Uri and authentication logic.
     */
    public function canConnect(UriInterface $uri, AuthenticateInterface $authenticate): bool;
}
