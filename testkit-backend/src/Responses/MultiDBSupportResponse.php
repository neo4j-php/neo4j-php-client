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
use Symfony\Component\Uid\Uuid;

/**
 * Specifies whether the server or cluster the driver connects to supports multi-databases.
 */
final class MultiDBSupportResponse implements TestkitResponseInterface
{
    private Uuid $id;
    private bool $available;

    public function __construct(Uuid $id, bool $available)
    {
        $this->id = $id;
        $this->available = $available;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'MultiDBSupport',
            'data' => [
                'id' => $this->id,
                'available' => $this->available,
            ],
        ];
    }
}
