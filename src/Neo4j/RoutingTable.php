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

use Ds\Set;
use function in_array;
use Laudis\Neo4j\Enum\RoutingRoles;

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

    public function getTtl(): int
    {
        return $this->ttl;
    }

    /**
     * @return Set<string>
     */
    public function getWithRole(RoutingRoles $role): Set
    {
        /** @psalm-var Set<string> $tbr */
        $tbr = new Set();
        foreach ($this->servers as $server) {
            if (in_array($server['role'], $role->getValue(), true)) {
                foreach ($server['addresses'] as $address) {
                    $tbr->add($address);
                }
            }
        }

        return $tbr;
    }
}
