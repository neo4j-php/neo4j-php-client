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
 * Response to ResultSingleOptional.
 */
final class RecordOptionalResponse implements TestkitResponseInterface
{
    /**
     * @param list<mixed>|null $recordValues First record as flat list of cell values, or null
     * @param list<string>     $warnings
     */
    public function __construct(
        private readonly ?array $recordValues,
        private readonly array $warnings,
    ) {
    }

    public function jsonSerialize(): array
    {
        $record = null;
        if ($this->recordValues !== null) {
            $values = [];
            foreach ($this->recordValues as $value) {
                $values[] = CypherObject::autoDetect($value);
            }
            $record = ['values' => $values];
        }

        return [
            'name' => 'RecordOptional',
            'data' => [
                'record' => $record,
                'warnings' => $this->warnings,
            ],
        ];
    }
}
