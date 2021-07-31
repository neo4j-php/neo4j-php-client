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
 * Represents a result instance on the backend.
 */
final class ResultResponse implements TestkitResponseInterface
{
    private Uuid $id;
    private iterable $keys;

    /**
     * @param iterable<string> $keys
     */
    public function __construct(Uuid $id, iterable $keys)
    {
        $this->id = $id;
        $this->keys = $keys;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'Result',
            'data' => [
                'id' => $this->id->toRfc4122(),
                'keys' => $this->keys,
            ],
        ];
    }
}
