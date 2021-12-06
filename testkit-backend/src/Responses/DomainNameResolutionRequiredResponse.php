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
 * Represents a need for new address resolution.
 *
 * This means that the backend expects the frontend to call the resolver function and submit a new request
 * with the results of it.
 */
final class DomainNameResolutionRequiredResponse implements TestkitResponseInterface
{
    private Uuid $id;
    private string $name;

    public function __construct(Uuid $id, string $name)
    {
        $this->id = $id;
        $this->name = $name;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'DomainNameResolutionRequired',
            'data' => [
                'id' => $this->id->toRfc4122(),
                'name' => $this->name,
            ],
        ];
    }
}
