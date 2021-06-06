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

use BadMethodCallException;
use Bolt\structures\Node as BoltNode;
use Ds\Map;
use Ds\Vector;

final class Node
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

    public function labels(): Vector
    {
        return $this->labels->copy();
    }

    public function properties(): array
    {
        return $this->properties->toArray();
    }

    public function id(): int
    {
        return $this->id;
    }

    public function property(string $key)
    {
        if ($this->properties->hasKey($key)) {
            return $this->properties->get($key);
        }
    }

    public function __get($key)
    {
        return $this->property($key);
    }

    public function __set($key, $value)
    {
        throw new BadMethodCallException(sprintf('% is immutable', get_class($this)));
    }
}
