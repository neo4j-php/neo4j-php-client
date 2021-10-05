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

namespace Laudis\Neo4j\Databags;

use Laudis\Neo4j\Enum\ConnectionProtocol;
use Psr\Http\Message\UriInterface;

/**
 * Provides some basic information of the server where the result is obtained from.
 *
 * @psalm-immutable
 */
final class ServerInfo
{
    private UriInterface $address;
    private ConnectionProtocol $protocol;
    private string $agent;

    public function __construct(UriInterface $address, ConnectionProtocol $protocol, string $agent)
    {
        $this->address = $address;
        $this->protocol = $protocol;
        $this->agent = $agent;
    }

    /**
     * Returns the uri of the server the query was executed.
     */
    public function getAddress(): UriInterface
    {
        return $this->address;
    }

    /**
     * Returns Connection Protocol version with which the remote server communicates.
     */
    public function getProtocol(): ConnectionProtocol
    {
        return $this->protocol;
    }

    /**
     * Returns server agent string by which the remote server identifies itself.
     */
    public function getAgent(): string
    {
        return $this->agent;
    }
}
