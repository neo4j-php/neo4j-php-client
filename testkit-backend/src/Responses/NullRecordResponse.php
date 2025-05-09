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
 * Represents the record end when iterating through it.
 */
final class NullRecordResponse implements TestkitResponseInterface
{
    public function jsonSerialize(): array
    {
        return [
            'name' => 'NullRecord',
        ];
    }
}
