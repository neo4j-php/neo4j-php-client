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

namespace Laudis\Neo4j\TestkitBackend\Requests;

use Symfony\Component\Uid\Uuid;

final class GetRoutingTableRequest
{
    private Uuid $driverId;
    private ?string $database;

    public function __construct(Uuid $driverId, ?string $database)
    {
        $this->driverId = $driverId;
        $this->database = $database;
    }

    public function getDriverId(): Uuid
    {
        return $this->driverId;
    }

    public function getDatabase(): ?string
    {
        return $this->database;
    }
}
