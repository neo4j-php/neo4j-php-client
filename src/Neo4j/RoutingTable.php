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

namespace Laudis\Neo4j\Neo4j;

use function in_array;
use Laudis\Neo4j\Enum\RoutingRoles;

/**
 * Table containing possible routes to nodes in the cluster.
 *
 * @psalm-immutable
 */
final class RoutingTable
{
    /** @var iterable<array{addresses: list<string>, role:string}> */
    private iterable $servers;
    private int $ttl;

    /**
     * @param iterable<array{addresses: list<string>, role:string}> $servers
     */
    public function __construct(iterable $servers, int $ttl)
    {
        $this->servers = $servers;
        $this->ttl = $ttl;
    }

    /**
     * Returns the time to live in seconds.
     */
    public function getTtl(): int
    {
        return $this->ttl;
    }

    /**
     * Returns the routes with a given role. If no role is provided it will return all routes.
     *
     * @return list<string>
     */
    public function getWithRole(RoutingRoles $role = null): array
    {
        /** @psalm-var list<string> $tbr */
        $tbr = [];
        foreach ($this->servers as $server) {
            if ($role === null || in_array($server['role'], $role->getValue(), true)) {
                foreach ($server['addresses'] as $address) {
                    $tbr[] = $address;
                }
            }
        }

        return array_values(array_unique($tbr));
    }
}
