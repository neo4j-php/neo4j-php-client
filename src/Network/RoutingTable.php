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

namespace Laudis\Neo4j\Network;

use Ds\Vector;
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
     * @return Vector<string>
     */
    public function getWithRole(RoutingRoles $role): Vector
    {
        /** @psalm-var Vector<string> $tbr */
        $tbr = new Vector();
        foreach ($this->servers as $server) {
            if ($server['role'] === $role->getValue()) {
                foreach ($server['addresses'] as $address) {
                    $tbr->push($address);
                }
            }
        }

        return $tbr;
    }
}
