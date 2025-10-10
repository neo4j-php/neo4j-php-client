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
use Symfony\Component\Uid\Uuid;
use Traversable;

/**
 * Response for ExecuteQuery containing an eager result.
 */
final class EagerResultResponse implements TestkitResponseInterface
{
    private Uuid $id;
    private array $keys;
    private array $records;

    public function __construct(Uuid $id, $eagerResult)
    {
        $this->id = $id;

        $this->keys = $eagerResult->getKeys()->toArray();

        $this->records = [];
        foreach ($eagerResult as $record) {
            $values = [];
            foreach ($record->values() as $value) {
                $values[] = $this->convertValue($value);
            }
            $this->records[] = ['values' => $values];
        }
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'EagerResult',
            'data' => [
                'id' => $this->id->toRfc4122(),
                'keys' => $this->keys,
                'records' => $this->records,
            ],
        ];
    }

    /**
     * Convert Neo4j values to testkit format.
     */
    private function convertValue($value)
    {
        if ($value === null) {
            return [
                'name' => 'CypherNull',
                'data' => ['value' => null],
            ];
        }

        if (is_int($value)) {
            return [
                'name' => 'CypherInt',
                'data' => ['value' => $value],
            ];
        }

        if (is_float($value)) {
            return [
                'name' => 'CypherFloat',
                'data' => ['value' => $value],
            ];
        }

        if (is_string($value)) {
            return [
                'name' => 'CypherString',
                'data' => ['value' => $value],
            ];
        }

        if (is_bool($value)) {
            return [
                'name' => 'CypherBool',
                'data' => ['value' => $value],
            ];
        }

        if (is_array($value) || $value instanceof Traversable) {
            $values = [];
            foreach ($value as $item) {
                $values[] = $this->convertValue($item);
            }

            return [
                'name' => 'CypherList',
                'data' => ['value' => $values],
            ];
        }

        if (is_object($value)) {
            return [
                'name' => 'CypherMap',
                'data' => ['value' => (array) $value],
            ];
        }

        return [
            'name' => 'CypherString',
            'data' => ['value' => (string) $value],
        ];
    }
}
