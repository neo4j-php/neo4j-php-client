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

namespace Laudis\Neo4j\Formatter\Specialised;

use function is_array;

use stdClass;

/**
 * @psalm-immutable
 */
final class HttpMetaInfo
{
    /**
     * @param list<stdClass> $meta
     * @param list<stdClass> $nodes
     * @param list<stdClass> $relationships
     */
    public function __construct(
        private array $meta,
        private array $nodes,
        private array $relationships,
        private int $currentMeta = 0
    ) {}

    /**
     * @pure
     */
    public static function createFromData(stdClass $data): self
    {
        /** @var stdClass */
        $graph = $data->graph;

        /** @psalm-suppress MixedArgument */
        return new self($data->meta, $graph->nodes, $graph->relationships);
    }

    /**
     * @return stdClass|list<stdClass>|null
     */
    public function currentMeta()
    {
        return $this->meta[$this->currentMeta] ?? null;
    }

    public function currentNode(): ?stdClass
    {
        $meta = $this->currentMeta();
        if ($meta === null || is_array($meta)) {
            return null;
        }

        foreach ($this->nodes as $node) {
            if ((int) $node->id === $meta->id) {
                return $node;
            }
        }

        return null;
    }

    public function getCurrentRelationship(): ?stdClass
    {
        $meta = $this->currentMeta();
        if ($meta === null || is_array($meta)) {
            return null;
        }

        foreach ($this->relationships as $relationship) {
            if ((int) $relationship->id === $meta->id) {
                return $relationship;
            }
        }

        return null;
    }

    public function getCurrentType(): ?string
    {
        $currentMeta = $this->currentMeta();
        if (is_array($currentMeta)) {
            return 'path';
        }

        if ($currentMeta === null) {
            return null;
        }

        /** @var string */
        return $currentMeta->type;
    }

    public function withNestedMeta(): self
    {
        $tbr = clone $this;

        $currentMeta = $this->currentMeta();
        if (is_array($currentMeta)) {
            $tbr->meta = $currentMeta;
            $tbr->currentMeta = 0;
        }

        return $tbr;
    }

    public function incrementMeta(): self
    {
        $tbr = clone $this;
        ++$tbr->currentMeta;

        return $tbr;
    }
}
