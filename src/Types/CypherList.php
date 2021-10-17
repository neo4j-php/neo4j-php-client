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
use function array_key_last;
use function array_slice;
use function is_int;
use OutOfBoundsException;
use function sort;
use function usort;

/**
 * An immutable ordered sequence of items.
 *
 * @template TValue
 *
 * @extends AbstractCypherSequence<int, TValue>
 *
 * @psalm-immutable
 */
final class CypherList extends AbstractCypherSequence
{
    /**
     * @param iterable<TValue> $iterable
     */
    public function __construct(iterable $iterable = [])
    {
        if ($iterable instanceof self) {
            /** @psalm-suppress InvalidPropertyAssignmentValue */
            $this->sequence = $iterable->sequence;
        } else {
            $this->sequence = [];
            foreach ($iterable as $value) {
                $this->sequence[] = $value;
            }
        }
    }

    /**
     * Returns the first element in the sequence.
     *
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
     * Returns the last element in the sequence.
     *
     * @return TValue
     */
    public function last()
    {
        $key = array_key_last($this->sequence);
        if (!is_int($key)) {
            throw new OutOfBoundsException('Cannot grab last element of an empty list');
        }

        return $this->sequence[$key];
    }

    /**
     * @param iterable<TValue> $values
     *
     * @return CypherList<TValue>
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
     * @param (pure-callable(TValue, TValue):int)|null $comparator
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

    /**
     * Gets the nth element in the list.
     *
     * @throws OutOfBoundsException
     *
     * @return TValue
     */
    public function get(int $key)
    {
        if (!array_key_exists($key, $this->sequence)) {
            throw new OutOfBoundsException(sprintf('Cannot get item in sequence at position: %s', $key));
        }

        return $this->sequence[$key];
    }
}
