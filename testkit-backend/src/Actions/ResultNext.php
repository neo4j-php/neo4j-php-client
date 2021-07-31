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

namespace Laudis\Neo4j\TestkitBackend\Actions;

use Ds\Map;
use Iterator;
use Laudis\Neo4j\TestkitBackend\Contracts\ActionInterface;
use function is_float;
use function is_int;

final class ResultNext implements ActionInterface
{
    private Map $results;

    public function __construct(Map $results)
    {
        $this->results = $results;
    }

    public function handle(array $data): array
    {
        /** @var Iterator $iterator */
        $iterator = $this->results->get($data['resultId']);

        if (!$iterator->valid()) {
            return [];
        }

        $current = $iterator->current()->values();
        $iterator->next();

        $values = [];
        foreach ($current as $value) {
            if (is_int($value)) {
                $values[] = ['name' => 'CypherInt', 'data' => ['value' => $value]];
            } else {
                $values[] = $value;
            }
        }

        return [
            'name' => 'Record',
            'data' => [
                'values' => $values,
            ],
        ];
    }
}
