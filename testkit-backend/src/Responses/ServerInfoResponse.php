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

namespace Laudis\Neo4j\TestkitBackend\Responses;

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;

/**
 * Represents server info included in the Summary response.
 */
final class ServerInfoResponse implements TestkitResponseInterface
{
    private string $address;
    private string $agent;
    private string $protocolVersion;

    public function __construct(string $address, string $agent, string $protocolVersion)
    {
        $this->address = $address;
        $this->agent = $agent;
        $this->protocolVersion = $protocolVersion;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'ServerInfo',
            'data' => [
                'address' => $this->address,
                'agent' => $this->agent,
                'protocol_version' => $this->protocolVersion,
            ],
        ];
    }
}
