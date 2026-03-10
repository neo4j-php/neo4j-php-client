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
 * Represents list of records returned from ResultList request.
 */
final class RecordListResponse implements TestkitResponseInterface
{
    /**
     * @param list<iterable<TestkitResponseInterface>> $records
     */
    public function __construct(
        private readonly array $records,
    ) {
    }

    public function jsonSerialize(): array
    {
        $records = [];
        foreach ($this->records as $record) {
            $records[] = ['values' => $record];
        }

        return [
            'name' => 'RecordList',
            'data' => [
                'records' => $records,
            ],
        ];
    }
}
