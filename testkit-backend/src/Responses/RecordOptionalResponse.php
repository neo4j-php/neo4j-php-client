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
 * Represents optional record from ResultSingleOptional request.
 */
final class RecordOptionalResponse implements TestkitResponseInterface
{
    /**
     * @param iterable<TestkitResponseInterface>|null $record
     * @param list<string>                            $warnings
     */
    public function __construct(
        private readonly ?iterable $record,
        private readonly array $warnings = [],
    ) {
    }

    public function jsonSerialize(): array
    {
        $data = [
            'record' => null,
            'warnings' => $this->warnings,
        ];

        if ($this->record !== null) {
            $data['record'] = ['values' => $this->record];
        }

        return [
            'name' => 'RecordOptional',
            'data' => $data,
        ];
    }
}
