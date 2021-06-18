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
use Ds\Vector;
use Laudis\Neo4j\Contracts\CypherContainerInterface;
use Traversable;

/**
 * @template T
 *
 * @implements CypherContainerInterface<int, T>
 */
final class CypherList implements CypherContainerInterface
{
    /** @var Vector<T> */
    private Vector $vector;

    /**
     * @param Vector<T> $vector
     */
    public function __construct(Vector $vector)
    {
        $this->vector = $vector;
    }

    public function count(): int
    {
        return $this->vector->count();
    }

    /**
     * @return CypherList<T>
     */
    public function copy(): CypherList
    {
        return new CypherList($this->vector->copy());
    }

    public function isEmpty(): bool
    {
        return $this->vector->isEmpty();
    }

    /**
     * @return list<T>
     */
    public function toArray(): array
    {
        return $this->vector->toArray();
    }

    public function getIterator()
    {
        /** @var Traversable<int, T> */
        return $this->vector->getIterator();
    }

    public function offsetExists($offset): bool
    {
        return $this->vector->offsetExists($offset);
    }

    /**
     * @psalm-suppress InvalidReturnType
     *
     * @param int $offset
     *
     * @return T
     */
    public function offsetGet($offset)
    {
        /** @psalm-suppress InvalidReturnStatement */
        return $this->vector->offsetGet($offset);
    }

    /**
     * @param int $offset
     * @param T   $value
     */
    public function offsetSet($offset, $value)
    {
        throw new BadMethodCallException('A cypher list is immutable');
    }

    /**
     * @param int $offset
     */
    public function offsetUnset($offset)
    {
        throw new BadMethodCallException('A cypher list is immutable');
    }

    /**
     * @param T ...$values
     */
    public function contains(...$values): bool
    {
        return $this->vector->contains(...$values);
    }

    /**
     * @param (callable(T):bool)|null $callback
     *
     * @return CypherList<T>
     */
    public function filter(callable $callback = null): CypherList
    {
        return new CypherList($this->vector->filter($callback));
    }

    /**
     * @param T $value
     *
     * @return false|int
     */
    public function find($value)
    {
        return $this->vector->find($value);
    }

    /**
     * @return T
     */
    public function first()
    {
        return $this->vector->first();
    }

    /**
     * @return T
     */
    public function get(int $index)
    {
        return $this->vector->get($index);
    }

    public function join(?string $glue = null): string
    {
        if ($glue === null) {
            return $this->vector->join();
        }

        return $this->vector->join($glue);
    }

    /**
     * @return T
     */
    public function last()
    {
        return $this->vector->last();
    }

    /**
     * @template U
     *
     * @param callable(T):U $callback
     *
     * @return CypherList<U>
     */
    public function map(callable $callback): CypherList
    {
        return new CypherList($this->vector->map($callback));
    }

    /**
     * @param iterable<T> $values
     *
     * @return CypherList<T>
     */
    public function merge($values): CypherList
    {
        return new CypherList($this->vector->merge($values));
    }

    /**
     * @param callable(T, T|null):T $callback
     * @param T|null                $initial
     *
     * @return T|null
     */
    public function reduce(callable $callback, $initial = null)
    {
        return $this->vector->reduce($callback, $initial);
    }

    /**
     * @return CypherList<T>
     */
    public function reversed(): CypherList
    {
        return new CypherList($this->vector->reversed());
    }

    public function slice(int $index, int $length = null): CypherList
    {
        return new CypherList($this->vector->slice($index, $length));
    }

    /**
     * @param (callable(T,T):int)|null $comparator
     *
     * @return CypherList<T>
     */
    public function sorted(callable $comparator = null): CypherList
    {
        return new CypherList($this->vector->sorted($comparator));
    }

    /**
     * @return float|int
     */
    public function sum()
    {
        return $this->vector->sum();
    }

    public function jsonSerialize()
    {
        return $this->vector->jsonSerialize();
    }

    /**
     * @return list<T>
     */
    public function __debugInfo()
    {
        return $this->vector->toArray();
    }
}
