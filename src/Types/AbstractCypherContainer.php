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

use function array_key_exists;
use ArrayAccess;
use BadMethodCallException;
use function get_class;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use function sprintf;

/**
 * @implements ArrayAccess<string, static|scalar|null>
 * @implements IteratorAggregate<string, static|scalar|null>
 */
abstract class AbstractCypherContainer implements JsonSerializable, ArrayAccess, IteratorAggregate
{
    /** @var array<string, self|scalar|null>|null */
    private ?array $cachedSerialized = null;

    /**
     * @return array<string, static|scalar|null>
     */
    final public function jsonSerialize(): array
    {
        if ($this->cachedSerialized === null) {
            $tbr = [];

            foreach ($this as $key => $value) {
                $tbr[$key] = $value;
            }

            $this->cachedSerialized = $tbr;
        }

        return $this->cachedSerialized;
    }

    final public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->jsonSerialize());
    }

    final public function offsetGet($offset)
    {
        $serialized = $this->jsonSerialize();
        if (!array_key_exists($offset, $serialized)) {
            throw new InvalidArgumentException("Offset: $offset does not exists for class: ".static::class);
        }

        /** @psalm-suppress InvalidReturnStatement */
        return $serialized[$offset];
    }

    final public function offsetSet($offset, $value): void
    {
        throw new BadMethodCallException(sprintf('%s is immutable', get_class($this)));
    }

    final public function offsetUnset($offset): void
    {
        throw new BadMethodCallException(sprintf('%s is immutable', get_class($this)));
    }
}
