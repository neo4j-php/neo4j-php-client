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

namespace Laudis\Neo4j\TestkitBackend\Responses;

use Laudis\Neo4j\Databags\ServerInfo;
use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;

/**
 * Response containing server information.
 */
final class ServerInfoResponse implements TestkitResponseInterface
{
    private string $address;
    private string $agent;
    private string $protocolVersion;

    public function __construct(ServerInfo $serverInfo)
    {
        $uri = $serverInfo->getAddress();
        $this->address = $uri->getHost().':'.$uri->getPort();

        $this->agent = $serverInfo->getAgent();

        $protocol = $serverInfo->getProtocol();
        if (method_exists($protocol, 'getValue')) {
            $this->protocolVersion = (string) $protocol->getValue();
        } else {
            $this->protocolVersion = (string) $protocol;
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'ServerInfo',
            'data' => [
                'address' => $this->address,
                'agent' => $this->agent,
                'protocolVersion' => $this->protocolVersion,
            ],
        ];
    }
}
