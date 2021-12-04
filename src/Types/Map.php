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
use function is_iterable;
use function is_object;
use function is_string;
use function ksort;
use Laudis\Neo4j\Databags\Pair;
use Laudis\Neo4j\Exception\RuntimeTypeException;
use Laudis\Neo4j\TypeCaster;
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
 * @extends AbstractCypherSequence<TValue, string>
 *
 * @psalm-immutable
 */
class Map extends AbstractCypherSequence
{
    /**
     * @param iterable<mixed, TValue> $iterable
     */
    final public function __construct(iterable $iterable = [])
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
     * @return ArrayList<string>
     */
    public function keys(): ArrayList
    {
        return new ArrayList(array_keys($this->sequence));
    }

    /**
     * Returns the pairs in the map in order.
     *
     * @return ArrayList<Pair<string, TValue>>
     */
    public function pairs(): ArrayList
    {
        $tbr = [];
        foreach ($this->sequence as $key => $value) {
            $tbr[] = new Pair($key, $value);
        }

        return new ArrayList($tbr);
    }

    /**
     * Create a new map sorted by keys. Natural ordering will be used if no comparator is provided.
     *
     * @param (callable(string, string):int)|null $comparator
     *
     * @return static<TValue>
     */
    public function ksorted(callable $comparator = null): Map
    {
        $tbr = $this->sequence;
        if ($comparator === null) {
            ksort($tbr);
        } else {
            /** @psalm-suppress ImpureFunctionCall */
            uksort($tbr, $comparator);
        }

        return $this->withIterable($tbr);
    }

    /**
     * Returns the values in the map in order.
     *
     * @return ArrayList<TValue>
     */
    public function values(): ArrayList
    {
        return new ArrayList($this->sequence);
    }

    /**
     * Creates a new map using exclusive or on the keys.
     *
     * @param iterable<array-key, TValue> $map
     *
     * @return static<TValue>
     */
    public function xor(iterable $map): Map
    {
        $tbr = $this->sequence;

        foreach ($map as $key => $value) {
            if (array_key_exists($key, $this->sequence)) {
                unset($tbr[(string) $key]);
            } else {
                $tbr[(string) $key] = $value;
            }
        }

        return $this->withIterable($tbr);
    }

    /**
     * @param iterable<array-key, TValue> $values
     *
     * @return static<TValue>
     */
    public function merge(iterable $values): Map
    {
        $tbr = $this->sequence;

        foreach ($values as $key => $value) {
            $tbr[$key] = $value;
        }

        return $this->withIterable($tbr);
    }

    /**
     * Creates a union of this and the provided map. The items in the original map take precedence.
     *
     * @param iterable<array-key, TValue> $map
     *
     * @return static<TValue>
     */
    public function union(iterable $map): Map
    {
        $tbr = $this->sequence;
        foreach ($map as $key => $value) {
            if (!array_key_exists($key, $tbr)) {
                $tbr[(string) $key] = $value;
            }
        }

        return $this->withIterable($tbr);
    }

    /**
     * Creates a new map from the existing one filtering the values based on the keys that don't exist in the provided map.
     *
     * @param iterable<array-key, TValue> $map
     *
     * @return static<TValue>
     */
    public function intersect(iterable $map): Map
    {
        $tbr = [];
        // @psalm-suppress UnusedForeachValue
        foreach ($map as $key => $value) {
            if (array_key_exists($key, $this->sequence)) {
                $tbr[$key] = $this->sequence[$key];
            }
        }

        return $this->withIterable($tbr);
    }

    /**
     * Creates a new map from the existing one filtering the values based on the keys that also exist in the provided map.
     *
     * @param iterable<array-key, TValue> $map
     *
     * @return static<TValue>
     */
    public function diff(iterable $map): Map
    {
        $tbr = $this->sequence;

        /** @psalm-suppress UnusedForeachValue */
        foreach ($map as $key => $value) {
            unset($tbr[(string) $key]);
        }

        return $this->withIterable($tbr);
    }

    /**
     * @return static<TValue>
     */
    public function reversed(): Map
    {
        return $this->withIterable(array_reverse($this->sequence, true));
    }

    /**
     * @return static<TValue>
     */
    public function slice(int $offset, int $length = null): Map
    {
        return $this->withIterable(array_slice($this->sequence, $offset, $length, true));
    }

    /**
     * @param (callable(TValue, TValue):int)|null $comparator
     *
     * @return static<TValue>
     */
    public function sorted(?callable $comparator = null): Map
    {
        $tbr = $this->sequence;
        if ($comparator === null) {
            asort($tbr);
        } else {
            /** @psalm-suppress ImpureFunctionCall */
            uasort($tbr, $comparator);
        }

        return $this->withIterable($tbr);
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

    /**
     * @param mixed $default
     */
    public function getAsString(string $key, $default = null): string
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        $tbr = TypeCaster::toString($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'string');
        }

        return $tbr;
    }

    /**
     * @param mixed $default
     */
    public function getAsInt(string $key, $default = null): int
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        $tbr = TypeCaster::toInt($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'int');
        }

        return $tbr;
    }

    /**
     * @param mixed $default
     */
    public function getAsFloat(string $key, $default = null): float
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        $tbr = TypeCaster::toFloat($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'float');
        }

        return $tbr;
    }

    /**
     * @param mixed $default
     */
    public function getAsBool(string $key, $default = null): bool
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        $tbr = TypeCaster::toBool($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'bool');
        }

        return $tbr;
    }

    /**
     * @param mixed $default
     *
     * @return null
     */
    public function getAsNull(string $key, $default = null)
    {
        if (func_num_args() === 1) {
            /** @psalm-suppress UnusedMethodCall */
            $this->get($key);
        }

        return TypeCaster::toNull();
    }

    /**
     * @template U
     *
     * @param class-string<U> $class
     * @param mixed           $default
     *
     * @return U
     */
    public function getAsObject(string $key, string $class, $default = null): object
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        $tbr = TypeCaster::toClass($value, $class);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, $class);
        }

        return $tbr;
    }

    /**
     * @param mixed $default
     *
     * @return Map<mixed>
     */
    public function getAsMap(string $key, $default = null): Map
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }

        if (!is_iterable($value)) {
            throw new RuntimeTypeException($value, Map::class);
        }

        return new Map($value);
    }

    /**
     * @param mixed $default
     *
     * @return ArrayList<mixed>
     */
    public function getAsArrayList(string $key, $default = null): ArrayList
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            /** @var mixed */
            $value = $this->get($key, $default);
        }
        if (!is_iterable($value)) {
            throw new RuntimeTypeException($value, ArrayList::class);
        }

        return new ArrayList($value);
    }

    /**
     * @template Value
     *
     * @param iterable<Value> $iterable
     *
     * @return Map<Value>
     *
     * @pure
     */
    public static function fromIterable(iterable $iterable): Map
    {
        return new self($iterable);
    }

    /**
     * @template Value
     *
     * @param iterable<mixed, Value> $iterable
     *
     * @return static<Value>
     */
    protected function withIterable(iterable $iterable): Map
    {
        /** @psalm-suppress UnsafeGenericInstantiation */
        return new static($iterable);
    }
}
