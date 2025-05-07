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

namespace Laudis\Neo4j\TestkitBackend\Responses\Types;

use Laudis\Neo4j\TestkitBackend\Contracts\TestkitResponseInterface;

final class CypherNode implements TestkitResponseInterface
{
    private CypherObject $id;
    private CypherObject $labels;
    private CypherObject $props;
    private CypherObject $elementId;

    public function __construct(CypherObject $id, CypherObject $labels, CypherObject $props, CypherObject $elementId)
    {
        $this->id = $id;
        $this->labels = $labels;
        $this->props = $props;
        $this->elementId = $elementId;
    }

    public function jsonSerialize(): array
    {
        return [
            'name' => 'CypherNode',
            'data' => [
                'id' => $this->id,
                'labels' => $this->labels,
                'props' => $this->props,
                'elementId' => $this->elementId,
            ],
        ];
    }
}
