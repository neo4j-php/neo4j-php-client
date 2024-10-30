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

use Laudis\Neo4j\Databags\DatabaseInfo;
use Laudis\Neo4j\Enum\AccessMode;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Psr\Http\Message\UriInterface;

/**
 * A connection is an abstraction over a protocol used to communicate between driver and server.
 *
 * @template ProtocolImplementation The implementation of the protocol.
 */
interface ConnectionInterface
{
    /**
     * @return ProtocolImplementation
     */
    public function getImplementation();

    /**
     * Returns the authentication logic attached to this connection.
     *
     * @psalm-mutation-free
     */
    public function getAuthentication(): AuthenticateInterface;

    /**
     * Returns the agent the servers uses to identify itself.
     *
     * @psalm-mutation-free
     */
    public function getServerAgent(): string;

    /**
     * Returns the Uri used to connect to the server.
     *
     * @psalm-mutation-free
     */
    public function getServerAddress(): UriInterface;

    /**
     * Returns the version of the neo4j server.
     *
     * @psalm-mutation-free
     */
    public function getServerVersion(): string;

    /**
     * Returns the assumed server state.
     */
    public function getServerState(): string;

    /**
     * Returns the protocol used to connect to the server.
     *
     * @psalm-mutation-free
     */
    public function getProtocol(): ConnectionProtocol;

    /**
     * Returns the mode of access.
     *
     * @psalm-mutation-free
     */
    public function getAccessMode(): AccessMode;

    /**
     * Returns the information about the database the connection reaches.
     *
     * @psalm-mutation-free
     */
    public function getDatabaseInfo(): ?DatabaseInfo;

    /**
     * Resets the connection.
     */
    public function reset(): void;

    /**
     * Sets the timeout of the connection in seconds.
     */
    public function setTimeout(float $timeout): void;

    /**
     * Checks to see if the connection is open.
     */
    public function isOpen(): bool;

    /**
     * Encryption level can be either '', 's' or 'ssc', which stand for 'no encryption', 'full encryption' and 'self-signed encryption' respectively.
     *
     * @return ''|'s'|'ssc'
     */
    public function getEncryptionLevel(): string;

    /**
     * Returns the user agent handling this connection.
     */
    public function getUserAgent(): string;

    /**
     * Closes the connection.
     */
    public function close(): void;
}
