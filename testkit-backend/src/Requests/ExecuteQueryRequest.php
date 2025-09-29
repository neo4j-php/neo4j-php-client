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

final class ExecuteQueryRequest
{
    private Uuid $driverId;
    private string $cypher;
    private ?array $params;
    private ?array $config;

    public function __construct(
        Uuid $driverId,
        string $cypher,
        ?array $params = null,
        ?array $config = null
    ) {
        $this->driverId = $driverId;
        $this->cypher = $cypher;
        $this->params = $params;
        $this->config = $config;
    }

    public function getDriverId(): Uuid
    {
        return $this->driverId;
    }

    public function getCypher(): string
    {
        return $this->cypher;
    }

    public function getParams(): ?array
    {
        return $this->params;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }
}
