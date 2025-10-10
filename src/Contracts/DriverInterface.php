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

use Laudis\Neo4j\Databags\ServerInfo;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;

/**
 * The driver creates sessions for carrying out work.
 *
 * @psalm-type ParsedUrl = array{host: string, pass: string|null, path: string, port: int, query: array<string,string>, scheme: string, user: string|null}
 * @psalm-type BasicDriver = DriverInterface<CypherList<CypherMap<string, scalar|array|null>>>
 */
interface DriverInterface
{
    /**
     * @psalm-mutation-free
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface;

    /**
     * Returns true if the driver can make a valid connection with the server.
     */
    public function verifyConnectivity(?SessionConfiguration $config = null): bool;

    /**
     * Gets server information without running a query.
     *
     * Acquires a connection from the pool and extracts server metadata.
     * The pool handles all connection management, routing, and retries.
     */
    public function getServerInfo(?SessionConfiguration $config = null): ServerInfo;

    /**
     * Closes all connections in the pool.
     */
    public function closeConnections(): void;
}
