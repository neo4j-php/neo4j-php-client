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
use function get_class;
use InvalidArgumentException;
use Laudis\Neo4j\Contracts\CypherContainerInterface;
use function sprintf;

/**
 * @implements CypherContainerInterface<string, CypherContainerInterface|scalar|null>
 */
abstract class AbstractCypherContainer implements CypherContainerInterface
{
    private ?array $cachedSerialized = null;

    public function jsonSerialize(): array
    {
        if ($this->cachedSerialized) {
            return $this->cachedSerialized;
        }

        $tbr = [];

        foreach ($this as $key => $value) {
            $tbr[$key] = $value;
        }

        $this->cachedSerialized = $tbr;

        return $tbr;
    }

    public function offsetExists($offset): bool
    {
        return isset($this->jsonSerialize()[$offset]);
    }

    public function offsetGet($offset)
    {
        $serialized = $this->jsonSerialize();
        if (!isset($serialized[$offset])) {
            throw new InvalidArgumentException("Offset: $offset does not exists for class: ".static::class);
        }

        return $serialized[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        throw new BadMethodCallException(sprintf('%s is immutable', get_class($this)));
    }

    public function offsetUnset($offset): void
    {
        throw new BadMethodCallException(sprintf('%s is immutable', get_class($this)));
    }
}
