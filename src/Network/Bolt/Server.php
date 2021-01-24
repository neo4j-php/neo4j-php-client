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

class Server
{
    private array $addresses;
    private string $role;

    public function __construct(array $addresses, string $role)
    {
        $this->addresses = $addresses;
        $this->role = $role;
    }

    public function getHost(): string
    {
        if (count($this->addresses) > 1) {
            return array_rand($this->addresses);
        }

        return $this->addresses[0];
    }
}
