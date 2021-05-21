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

namespace Laudis\Neo4j\Http;

use Laudis\Neo4j\Contracts\ConnectionPoolInterface;
use Laudis\Neo4j\Enum\AccessMode;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\UriInterface;

/**
 * @implements ConnectionPoolInterface<ClientInterface>
 */
final class HttpConnectionPool implements ConnectionPoolInterface
{
    private ClientInterface $client;

    public function __construct(ClientInterface $client)
    {
        $this->client = $client;
    }

    public function acquire(UriInterface $uri, AccessMode $mode): ClientInterface
    {
        return $this->client;
    }
}
