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

use Exception;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Types\AbstractCypherContainer;
use Psr\Http\Message\UriInterface;
use Traversable;

final class ServerInfo extends AbstractCypherContainer
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

    public function getAddress(): UriInterface
    {
        return $this->address;
    }

    public function getProtocol(): ConnectionProtocol
    {
        return $this->protocol;
    }

    public function getAgent(): string
    {
        return $this->agent;
    }

    public function getIterator()
    {
        yield 'address' => $this->address;
        yield 'protocol' => $this->protocol;
        yield 'agent' => $this->agent;
    }
}
