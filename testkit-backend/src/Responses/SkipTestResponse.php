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
 * Indicates the test should be skipped.
 */
final class SkipTestResponse implements TestkitResponseInterface
{
    public function __construct(private string $reason)
    {
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'SkipTest',
            'data' => [
                'reason' => $this->reason,
            ],
        ];
    }
}
