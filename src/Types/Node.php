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

namespace Laudis\Neo4j\Types;

use Ds\Map;
use Ds\Vector;
use Bolt\structures\Node as BoltNode;

class Node
{
    private int $id;
    private Vector $labels;
    private Map $properties;

    public function __construct(int $id, Vector $labels, Map $properties)
    {
        $this->id = $id;
        $this->labels = $labels;
        $this->properties = $properties;
    }

    public static function makeFromBoltNode(BoltNode $node): self
    {
        return new self(
            $node->id(),
            new Vector($node->labels()),
            new Map($node->properties())
        );
    }

    public static function makeFromHttpNode(array $node): self
    {
        return new self(
            (int) $node['id'],
            new Vector($node['labels']),
            new Map($node['properties']),
        );
    }

    public function labels(): array
    {
        return $this->labels->toArray();
    }

    public function properties(): array
    {
        return $this->properties->toArray();
    }

    public function id(): int
    {
        return $this->id;
    }
}
