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

namespace Laudis\Neo4j\TestkitBackend\Responses;

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;

/**
 * Represents the full routing table.
 */
final class RoutingTableResponse implements TestkitResponseInterface
{
    private ?string $database;
    private int $ttl;
    /** @var iterable<string> */
    private iterable $routers;
    /** @var iterable<string> */
    private iterable $readers;
    /** @var iterable<string> */
    private iterable $writers;

    /**
     * @param iterable<string> $routers
     * @param iterable<string> $readers
     * @param iterable<string> $writers
     */
    public function __construct(?string $database, int $ttl, iterable $routers, iterable $readers, iterable $writers)
    {
        $this->database = $database;
        $this->ttl = $ttl;
        $this->routers = $routers;
        $this->readers = $readers;
        $this->writers = $writers;
    }

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
