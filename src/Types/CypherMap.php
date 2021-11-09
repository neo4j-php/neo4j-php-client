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
use function func_num_args;
use InvalidArgumentException;
use function is_int;
use function is_object;
use function is_string;
use function ksort;
use Laudis\Neo4j\Databags\Pair;
use function method_exists;
use OutOfBoundsException;
use stdClass;
use function uasort;
use function uksort;

/**
 * An immutable ordered map of items.
 *
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
     * @param iterable<TValue> $iterable
     */
    public function __construct(iterable $iterable = [])
    {
        if ($iterable instanceof self) {
            /** @psalm-suppress InvalidPropertyAssignmentValue */
            $this->sequence = $iterable->sequence;
        } else {
            $this->sequence = [];
            /** @var mixed $key */
            foreach ($iterable as $key => $value) {
                if ($key === null || is_int($key) || (is_object($key) && method_exists($key, '__toString'))) {
                    $this->sequence[(string) $key] = $value;
                } elseif (is_string($key)) {
                    $this->sequence[$key] = $value;
                } else {
                    throw new InvalidArgumentException('Iterable must have a stringable keys');
                }
            }
        }
    }

    /**
     * Returns the first pair in the map.
     *
     * @return Pair<string, TValue>
     */
    public function first(): Pair
    {
        $key = array_key_first($this->sequence);
        if (!is_string($key)) {
            throw new OutOfBoundsException('Cannot grab first element of an empty map');
        }

        return new Pair($key, $this->sequence[$key]);
    }

    /**
     * Returns the last pair in the map.
     *
     * @return Pair<string, TValue>
     */
    public function last(): Pair
    {
        $key = array_key_last($this->sequence);
        if (!is_string($key)) {
            throw new OutOfBoundsException('Cannot grab last element of an empty map');
        }

        return new Pair($key, $this->sequence[$key]);
    }

    /**
     * Returns the pair at the nth position of the map.
     *
     * @return Pair<string, TValue>
     */
    public function skip(int $position): Pair
    {
        $keys = $this->keys();

        if ($keys->count() > $position) {
            $key = $keys[$position];

            return new Pair($key, $this->sequence[$key]);
        }

        throw new OutOfBoundsException(sprintf('Cannot skip to a pair at position: %s', $position));
    }

    /**
     * Returns the keys in the map in order.
     *
     * @return CypherList<string>
     */
    public function keys(): CypherList
    {
        return new CypherList(array_keys($this->sequence));
    }

    /**
     * Returns the pairs in the map in order.
     *
     * @return CypherList<Pair<string, TValue>>
     */
    public function pairs(): CypherList
    {
        $tbr = [];
        foreach ($this->sequence as $key => $value) {
            $tbr[] = new Pair($key, $value);
        }

        return new CypherList($tbr);
    }

    /**
     * Create a new map sorted by keys. Natural ordering will be used if no comparator is provided.
     *
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
     * Returns the values in the map in order.
     *
     * @return CypherList<TValue>
     */
    public function values(): CypherList
    {
        return new CypherList($this->sequence);
    }

    /**
     * Creates a new map using exclusive or on the keys.
     *
     * @param iterable<array-key, TValue> $map
     *
     * @return CypherMap<TValue>
     */
    public function xor(iterable $map): CypherMap
    {
        $tbr = $this->sequence;

        foreach ($map as $key => $value) {
            if (array_key_exists($key, $this->sequence)) {
                unset($tbr[(string) $key]);
            } else {
                $tbr[(string) $key] = $value;
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
     * Creates a union of this and the provided map. The items in the original map take precedence.
     *
     * @param iterable<array-key, TValue> $map
     *
     * @return CypherMap<TValue>
     */
    public function union(iterable $map): CypherMap
    {
        $tbr = $this->sequence;
        foreach ($map as $key => $value) {
            if (!array_key_exists($key, $tbr)) {
                $tbr[(string) $key] = $value;
            }
        }

        return new self($tbr);
    }

    /**
     * Creates a new map from the existing one filtering the values based on the keys that don't exist in the provided map.
     *
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
     * Creates a new map from the existing one filtering the values based on the keys that also exist in the provided map.
     *
     * @param iterable<array-key, TValue> $map
     *
     * @return CypherMap<TValue>
     */
    public function diff(iterable $map): CypherMap
    {
        $tbr = $this->sequence;

        /** @psalm-suppress UnusedForeachValue */
        foreach ($map as $key => $value) {
            unset($tbr[(string) $key]);
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
     * @param (pure-callable(TValue, TValue):int)|null $comparator
     *
     * @return static
     */
    public function sorted(?callable $comparator = null): self
    {
        $tbr = $this->sequence;
        if ($comparator === null) {
            asort($tbr);
        } else {
            uasort($tbr, $comparator);
        }

        return new self($tbr);
    }

    /**
     * Gets the value with the provided key. If a default value is provided, it will return the default instead of throwing an error when the key does not exist.
     *
     * @template TDefault
     *
     * @param TDefault $default
     *
     * @throws OutOfBoundsException
     *
     * @return (func_num_args() is 1 ? TValue : TValue|TDefault)
     */
    public function get(string $key, $default = null)
    {
        if (func_num_args() === 1) {
            if (!array_key_exists($key, $this->sequence)) {
                throw new OutOfBoundsException(sprintf('Cannot get item in sequence with key: %s', $key));
            }

            return $this->sequence[$key];
        }

        return $this->sequence[$key] ?? $default;
    }

    public function jsonSerialize()
    {
        if ($this->isEmpty()) {
            return new stdClass();
        }

        return parent::jsonSerialize();
    }
}
