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

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;

/**
 * Represents the full routing table.
 */
final class RoutingTableResponse implements TestkitResponseInterface
{
    /**
     * @param iterable<string> $routers
     * @param iterable<string> $readers
     * @param iterable<string> $writers
     */
    public function __construct(
        private ?string $database,
        private int $ttl,
        private iterable $routers,
        private iterable $readers,
        private iterable $writers
    ) {}

    public function jsonSerialize(): array
    {
        return [
            'name' => 'RoutingTable',
            'data' => [
                'database' => $this->database,
                'ttl' => $this->ttl,
                'routers' => $this->routers,
                'readers' => $this->readers,
                'writers' => $this->writers,
            ],
        ];
    }
}
