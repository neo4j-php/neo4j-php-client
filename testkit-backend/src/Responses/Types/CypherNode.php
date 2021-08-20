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

namespace Laudis\Neo4j\TestkitBackend\Responses\Types;

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;

final class CypherNode implements TestkitResponseInterface
{
    private CypherObject $id;
    private CypherObject $labels;
    private CypherObject $props;

    public function __construct(CypherObject $id, CypherObject $labels, CypherObject $props)
    {
        $this->id = $id;
        $this->labels = $labels;
        $this->props = $props;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'CypherNode',
            'data' => [
                'id' => $this->id,
                'labels' => $this->labels,
                'props' => $this->props,
            ],
        ];
    }
}
