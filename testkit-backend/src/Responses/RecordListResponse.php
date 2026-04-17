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
use Laudis\Neo4j\TestkitBackend\Responses\Types\CypherObject;

/**
 * Response to ResultList — full materialized record list.
 *
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Types\OGMTypesAlias
 */
final class RecordListResponse implements TestkitResponseInterface
{
    /**
     * @param list<list<mixed>> $records each row is a list of cell values (OGM types)
     */
    public function __construct(
        private readonly array $records,
    ) {
    }

    public function jsonSerialize(): array
    {
        $encoded = [];
        foreach ($this->records as $row) {
            $values = [];
            foreach ($row as $value) {
                $values[] = CypherObject::autoDetect($value);
            }
            $encoded[] = ['values' => $values];
        }

        return [
            'name' => 'RecordList',
            'data' => [
                'records' => $encoded,
            ],
        ];
    }
}
