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

namespace Laudis\Neo4j\Databags;

use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Types\AbstractCypherObject;
use Psr\Http\Message\UriInterface;

/**
 * Provides some basic information of the server where the result is obtained from.
 *
 * @psalm-immutable
 *
 * @extends AbstractCypherObject<string, mixed>
 */
final class ServerInfo extends AbstractCypherObject
{
    public function __construct(
        private readonly UriInterface $address,
        private readonly ConnectionProtocol $protocol,
        private readonly string $agent
    ) {}

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

    public function toArray(): array
    {
        return [
            'address' => $this->address,
            'protocol' => $this->protocol,
            'agent' => $this->agent,
        ];
    }
}
