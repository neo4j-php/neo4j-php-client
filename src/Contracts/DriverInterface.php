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
use Laudis\Neo4j\Formatter\CypherList;
use Laudis\Neo4j\Formatter\CypherMap;

/**
 * The driver creates sessions for carrying out work.
 *
 * @template ResultFormat
 *
 * @psalm-type ParsedUrl = array{host: string, pass: string|null, path: string, port: int, query: array<string,string>, scheme: string, user: string|null}
 * @psalm-type BasicDriver = DriverInterface<CypherList<CypherMap<string, scalar|array|null>>>
 */
interface DriverInterface
{
    /**
     * @return SessionInterface<ResultFormat>
     *
     * @psalm-mutation-free
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface;

    /**
     * Returns true if the driver can make a valid connection with the server.
     */
    public function verifyConnectivity(?SessionConfiguration $config = null): bool;

    /**
     * Closes all connections in the pool.
     */
    public function closeConnections(): void;
}
