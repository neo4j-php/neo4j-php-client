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

namespace Laudis\Neo4j\Network\Bolt;

use Bolt\Bolt;
use Ds\Map;
use Laudis\Neo4j\Formatter\BoltCypherFormatter;

class RoutingTable
{
    private $roles = [
        'leader' => 'WRITE',
        'follower' => 'READ',
        'router' => 'ROUTE',
    ];

    private Map $routingTable;
    private ?int $ttl = null;
    private Bolt $bolt;

    public function __construct(Bolt $bolt, BoltCypherFormatter $formatter)
    {
        $this->bolt = $bolt;
        $this->formatter = $formatter;
        $this->loadRoutingTable();
    }

    public function getLeaders(): ?Server
    {
        foreach ($this->getServers() as $server) {
            if ($server['role'] === $this->roles['leader']) {
                return new Server($server['addresses'], $server['role']);
            }
        }

        return null;
    }

    public function getFollowers(): ?Server
    {
        foreach ($this->getServers() as $server) {
            if ($server['role'] === $this->roles['follower']) {
                return new Server($server['addresses'], $server['roler']);
            }
        }
    }

    private function getServers(): array
    {
        return $this->loadRoutingTable()->get('servers');
    }

    private function loadRoutingTable(): Map
    {
        if (is_null($this->ttl) || $this->ttl > time()) {
            $meta = $this->bolt->run('CALL dbms.routing.getRoutingTable({})');
            $results = $this->bolt->pull();
            $response = $this->formatter->formatResult($meta, $results)->first();
            $this->ttl = time() + $response->get('ttl');
            $this->routingTable = $response;
        }

        return $this->routingTable;
    }
}
