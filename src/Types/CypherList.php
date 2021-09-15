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

use function array_filter;
use function array_key_exists;
use function array_key_last;
use function array_map;
use function array_reduce;
use function array_search;
use function array_slice;
use function array_sum;
use ArrayIterator;
use BadMethodCallException;
use function count;
use function in_array;
use function is_int;
use OutOfBoundsException;
use function sort;
use function usort;

/**
 * @template TValue
 *
 * @extends AbstractCypherSequence<int, TValue>
 *
 * @psalm-immutable
 */
final class CypherList extends AbstractCypherSequence
{
    /**
     * @param iterable<TValue> $array
     */
    public function __construct(iterable $array = [])
    {
        $this->sequence = [];
        foreach ($array as $value) {
            $this->sequence[] = $value;
        }
    }

    /**
     * @return TValue
     */
    public function first()
    {
        if (!array_key_exists(0, $this->sequence)) {
            throw new OutOfBoundsException('Cannot grab first element of an empty list');
        }

        return $this->sequence[0];
    }

    /**
     * @return TValue
     */
    public function get(int $index)
    {
        return $this->sequence[$index];
    }

    public function join(?string $glue = null): string
    {
        /** @psalm-suppress MixedArgumentTypeCoercion */
        return implode($glue ?? '', $this->sequence);
    }

    /**
     * @return T
     */
    public function last()
    {
        $key = array_key_last($this->sequence);
        if (!is_int($key)) {
            throw new BadMethodCallException('Cannot grab last element from an empty list');
        }

        return $this->sequence[$key];
    }

    /**
     * @param iterable<T> $values
     *
     * @return CypherList<T>
     */
    public function merge($values): CypherList
    {
        $tbr = $this->sequence;
        foreach ($values as $value) {
            $tbr[] = $value;
        }

        return new CypherList($tbr);
    }

    /**
     * @return CypherList<TValue>
     */
    public function reversed(): CypherList
    {
        return new CypherList(array_reverse($this->sequence));
    }

    /**
     * @return CypherList<TValue>
     */
    public function slice(int $offset, int $length = null): CypherList
    {
        return new CypherList(array_slice($this->sequence, $offset, $length));
    }

    /**
     * @param (callable(TValue,TValue):int)|null $comparator
     *
     * @return CypherList<TValue>
     */
    public function sorted(callable $comparator = null): CypherList
    {
        $tbr = $this->sequence;
        if ($comparator === null) {
            sort($tbr);
        } else {
            usort($tbr, $comparator);
        }

        return new CypherList($tbr);
    }


    /**
     * @pure
     */
    public static function fromIterable(iterable $iterable): AbstractCypherSequence
    {
        return new self($iterable);
    }
}
