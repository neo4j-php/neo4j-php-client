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
use function array_key_first;
use function array_key_last;
use function array_keys;
use function array_reverse;
use function array_slice;
use function array_values;
use BadMethodCallException;
use function func_num_args;
use function is_array;
use function is_string;
use function ksort;
use Laudis\Neo4j\Databags\Pair;
use OutOfBoundsException;
use function sort;
use function uksort;
use function usort;

/**
 * @template TValue
 *
 * @extends AbstractCypherSequence<string, TValue>
 *
 * @psalm-immutable
 */
final class CypherMap extends AbstractCypherSequence
{
    /**
     * @pure
     */
    public static function fromIterable(iterable $iterable): AbstractCypherSequence
    {
        return new self($iterable);
    }

    /**
     * @param iterable<array-key, TValue> $iterable
     */
    public function __construct(iterable $iterable = [])
    {
        if (is_array($iterable)) {
            $this->sequence = $iterable;
        } else {
            $this->sequence = [];
            foreach ($iterable as $key => $value) {
                $this->sequence[(string) $key] = $value;
            }
        }
    }

    /**
     * @return Pair<string, TValue>
     */
    public function first(): Pair
    {
        $key = array_key_first($this->sequence);
        if (!is_string($key)) {
            throw new BadMethodCallException('Cannot grab first element from an empty map');
        }

        return new Pair($key, $this->sequence[$key]);
    }

    /**
     * @return Pair<string, TValue>
     */
    public function last(): Pair
    {
        $key = array_key_last($this->sequence);
        if (!is_string($key)) {
            throw new BadMethodCallException('Cannot grab last element from an empty map');
        }

        return new Pair($key, $this->sequence[$key]);
    }

    /**
     * @return Pair<string, TValue>
     */
    public function skip(int $position): Pair
    {
        $keys = $this->keys();

        if (array_key_exists($position, $keys)) {
            $key = $keys[$position];

            return new Pair($key, $this->sequence[$key]);
        }

        throw new OutOfBoundsException();
    }

    /**
     * @template TDefault
     *
     * @param TDefault $default
     *
     * @throws OutOfBoundsException
     *
     * @return (
     *           func_num_args() is 1
     *           ? TValue
     *           : TValue|TDefault
     *           )
     *
     * @psalm-mutation-free
     */
    public function get(string $key, $default = null)
    {
        if (func_num_args() === 2) {
            return $this->sequence[$key] ?? $default;
        }

        if (!array_key_exists($key, $this->sequence)) {
            throw new OutOfBoundsException();
        }

        return $this->sequence[$key];
    }

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys($this->sequence);
    }

    /**
     * @return array<Pair<string, TValue>>
     */
    public function pairs(): array
    {
        $tbr = [];
        foreach ($this->sequence as $key => $value) {
            $tbr[] = new Pair($key, $value);
        }

        return $tbr;
    }

    /**
     * @param (callable(string, string):int)|null $comparator
     *
     * @return CypherMap<TValue>
     */
    public function ksorted(callable $comparator = null): CypherMap
    {
        $tbr = $this->sequence;
        if ($comparator === null) {
            ksort($tbr);
        } else {
            /** @psalm-suppress ImpureFunctionCall */
            uksort($tbr, $comparator);
        }

        return new self($tbr);
    }

    /**
     * @return list<TValue>
     */
    public function values(): array
    {
        return array_values($this->sequence);
    }

    /**
     * @param iterable<TValue> $map
     *
     * @return CypherMap<TValue>
     */
    public function xor(iterable $map): CypherMap
    {
        $tbr = [];
        foreach ($map as $key => $value) {
            if (!array_key_exists($key, $this->sequence)) {
                $tbr[$key] = $value;
            }
        }

        $cypherMap = new self($map);
        foreach ($this->sequence as $key => $value) {
            if (!array_key_exists($key, $cypherMap->sequence)) {
                $tbr[$key] = $value;
            }
        }

        return new self($tbr);
    }

    /**
     * @param iterable<array-key, TValue> $values
     *
     * @return static
     */
    public function merge(iterable $values): self
    {
        $other = new self($values);
        $tbr = $this->sequence;

        foreach ($other as $key => $value) {
            $tbr[$key] = $value;
        }

        return new self($tbr);
    }

    /**
     * @param iterable<array-key, TValue> $map
     *
     * @return CypherMap<TValue>
     */
    public function union(iterable $map): CypherMap
    {
        $tbr = $this->sequence;
        foreach ($map as $key => $value) {
            $tbr[(string) $key] = $value;
        }

        return new self($tbr);
    }

    /**
     * @param iterable<array-key, TValue> $map
     *
     * @return static
     */
    public function intersect(iterable $map): self
    {
        $tbr = [];
        // @psalm-suppress UnusedForeachValue
        foreach ($map as $key => $value) {
            if (array_key_exists($key, $this->sequence)) {
                $tbr[$key] = $this->sequence[$key];
            }
        }

        return $this::fromIterable($tbr);
    }

    /**
     * @param iterable<array-key, TValue> $map
     *
     * @return CypherMap<TValue>
     */
    public function diff($map): CypherMap
    {
        $tbr = $this->sequence;

        /** @psalm-suppress UnusedForeachValue */
        foreach ($map as $key => $value) {
            if (array_key_exists($key, $tbr)) {
                unset($tbr[$key]);
            }
        }

        return new self($tbr);
    }

    /**
     * @return static
     */
    public function reversed(): self
    {
        return $this::fromIterable(array_reverse($this->sequence, true));
    }

    /**
     * @return CypherMap<TValue>
     */
    public function slice(int $offset, int $length = null): self
    {
        return new self(array_slice($this->sequence, $offset, $length, true));
    }

    /**
     * @param (callable(TValue, TValue):int)|null $comparator
     *
     * @return static
     */
    public function sorted(?callable $comparator = null): self
    {
        $tbr = $this->sequence;
        if ($comparator === null) {
            sort($tbr);
        } else {
            /** @psalm-suppress ImpureFunctionCall */
            usort($tbr, $comparator);
        }

        return new self($tbr);
    }
}
