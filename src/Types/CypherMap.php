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

namespace Laudis\Neo4j\Types;

use ArrayAccess;
use ArrayIterator;

use function func_num_args;

use Generator;
use Iterator;
use Laudis\Neo4j\Contracts\CypherSequence;
use Laudis\Neo4j\Databags\Pair;
use Laudis\Neo4j\Exception\RuntimeTypeException;
use Laudis\Neo4j\TypeCaster;
use OutOfBoundsException;
use stdClass;

/**
 * An immutable ordered map of items.
 *
 * @template TValue
 *
 * @implements CypherSequence<TValue>
 * @implements ArrayAccess<string, TValue>
 * @implements Iterator<string, TValue>
 */
final class CypherMap implements CypherSequence, ArrayAccess, Iterator
{
    /**
     * @use CypherSequenceTrait<TValue>
     */
    use CypherSequenceTrait {
        jsonSerialize as jsonSerializeTrait;
    }

    /**
     * @param iterable<mixed, TValue>|callable():Generator<mixed, TValue> $iterable
     *
     * @psalm-mutation-free
     */
    public function __construct(iterable|callable $iterable = [])
    {
        if (is_array($iterable)) {
            $i = 0;
            foreach ($iterable as $key => $value) {
                if (!$this->isStringable($key)) {
                    $key = (string) $i;
                }
                /** @var string $key */
                $this->keyCache[] = $key;
                /** @var TValue $value */
                $this->cache[$key] = $value;
                ++$i;
            }
            /** @var ArrayIterator<string, TValue> */
            $it = new ArrayIterator([]);
            $this->generator = $it;
            $this->generatorPosition = count($this->keyCache);
        } else {
            $this->generator = function () use ($iterable): Generator {
                $i = 0;
                /** @var Generator<mixed, TValue> $it */
                $it = is_callable($iterable) ? $iterable() : $iterable;
                /** @var mixed $key */
                foreach ($it as $key => $value) {
                    if ($this->isStringable($key)) {
                        yield (string) $key => $value;
                    } else {
                        yield (string) $i => $value;
                    }
                    ++$i;
                }
            };
        }
    }

    /**
     * @template Value
     *
     * @param callable():(Generator<mixed, Value>) $operation
     *
     * @return self<Value>
     *
     * @psalm-mutation-free
     */
    protected function withOperation(callable $operation): CypherMap
    {
        return new self($operation);
    }

    /**
     * Returns the first pair in the map.
     *
     * @return Pair<string, TValue>
     */
    public function first(): Pair
    {
        foreach ($this as $key => $value) {
            return new Pair($key, $value);
        }
        throw new OutOfBoundsException('Cannot grab first element of an empty map');
    }

    /**
     * Returns the last pair in the map.
     *
     * @return Pair<string, TValue>
     */
    public function last(): Pair
    {
        $array = $this->toArray();
        if (count($array) === 0) {
            throw new OutOfBoundsException('Cannot grab last element of an empty map');
        }

        $key = array_key_last($array);

        return new Pair($key, $array[$key]);
    }

    /**
     * Returns the pair at the nth position of the map.
     *
     * @return Pair<string, TValue>
     */
    public function skip(int $position): Pair
    {
        $i = 0;
        foreach ($this as $key => $value) {
            if ($i === $position) {
                return new Pair($key, $value);
            }
            ++$i;
        }

        throw new OutOfBoundsException(sprintf('Cannot skip to a pair at position: %s', $position));
    }

    /**
     * Returns the keys in the map in order.
     *
     * @return CypherList<string>
     *
     * @psalm-suppress UnusedForeachValue
     */
    public function keys(): CypherList
    {
        return CypherList::fromIterable((function () {
            foreach ($this as $key => $value) {
                yield $key;
            }
        })());
    }

    /**
     * Returns the pairs in the map in order.
     *
     * @return CypherList<Pair<string, TValue>>
     */
    public function pairs(): CypherList
    {
        return CypherList::fromIterable((function () {
            foreach ($this as $key => $value) {
                yield new Pair($key, $value);
            }
        })());
    }

    /**
     * Create a new map sorted by keys. Natural ordering will be used if no comparator is provided.
     *
     * @param (callable(string, string):int)|null $comparator
     *
     * @return self<TValue>
     */
    public function ksorted(?callable $comparator = null): CypherMap
    {
        return $this->withOperation(function () use ($comparator) {
            $pairs = $this->pairs()->sorted(static function (Pair $x, Pair $y) use ($comparator) {
                if ($comparator !== null) {
                    return $comparator($x->getKey(), $y->getKey());
                }

                return $x->getKey() <=> $y->getKey();
            });

            foreach ($pairs as $pair) {
                yield $pair->getKey() => $pair->getValue();
            }
        });
    }

    /**
     * Returns the values in the map in order.
     *
     * @return CypherList<TValue>
     */
    public function values(): CypherList
    {
        return CypherList::fromIterable((function () {
            yield from $this;
        })());
    }

    /**
     * Creates a new map using exclusive or on the keys.
     *
     * @param iterable<array-key, TValue> $map
     *
     * @return self<TValue>
     */
    public function xor(iterable $map): CypherMap
    {
        return $this->withOperation(function () use ($map) {
            $map = CypherMap::fromIterable($map);
            foreach ($this as $key => $value) {
                if (!$map->hasKey($key)) {
                    yield $key => $value;
                }
            }

            foreach ($map as $key => $value) {
                if (!$this->hasKey($key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * @template NewValue
     *
     * @param iterable<mixed, NewValue> $values
     *
     * @psalm-suppress LessSpecificImplementedReturnType
     *
     * @return self<TValue|NewValue>
     *
     * @psalm-mutation-free
     */
    public function merge(iterable $values): CypherMap
    {
        return $this->withOperation(function () use ($values) {
            $tbr = $this->toArray();
            $values = CypherMap::fromIterable($values);

            foreach ($values as $key => $value) {
                $tbr[$key] = $value;
            }

            yield from $tbr;
        });
    }

    /**
     * Creates a union of this and the provided map. The items in the original map take precedence.
     *
     * @param iterable<mixed, TValue> $map
     *
     * @return self<TValue>
     */
    public function union(iterable $map): CypherMap
    {
        return $this->withOperation(function () use ($map) {
            $map = CypherMap::fromIterable($map)->toArray();
            $x = $this->toArray();

            yield from $x;

            foreach ($map as $key => $value) {
                if (!array_key_exists($key, $x)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Creates a new map from the existing one filtering the values based on the keys that don't exist in the provided map.
     *
     * @param iterable<array-key, TValue> $map
     *
     * @return self<TValue>
     */
    public function intersect(iterable $map): CypherMap
    {
        return $this->withOperation(function () use ($map) {
            $map = CypherMap::fromIterable($map)->toArray();
            foreach ($this as $key => $value) {
                if (array_key_exists($key, $map)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * Creates a new map from the existing one filtering the values based on the keys that also exist in the provided map.
     *
     * @param iterable<array-key, TValue> $map
     *
     * @return self<TValue>
     */
    public function diff(iterable $map): CypherMap
    {
        return $this->withOperation(function () use ($map) {
            $map = CypherMap::fromIterable($map)->toArray();
            foreach ($this as $key => $value) {
                if (!array_key_exists($key, $map)) {
                    yield $key => $value;
                }
            }
        });
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
        if (!$this->offsetExists($key)) {
            if (func_num_args() === 1) {
                throw new OutOfBoundsException(sprintf('Cannot get item in sequence with key: %s', $key));
            }

            return $default;
        }

        return $this->offsetGet($key);
    }

    public function jsonSerialize(): mixed
    {
        if ($this->isEmpty()) {
            return new stdClass();
        }

        return $this->jsonSerializeTrait();
    }

    public function getAsString(string $key): string
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            $value = $this->get($key);
        }
        $tbr = TypeCaster::toString($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'string');
        }

        return $tbr;
    }

    public function getAsInt(string $key): int
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            $value = $this->get($key);
        }
        $tbr = TypeCaster::toInt($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'int');
        }

        return $tbr;
    }

    public function getAsFloat(string $key): float
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            $value = $this->get($key);
        }
        $tbr = TypeCaster::toFloat($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'float');
        }

        return $tbr;
    }

    public function getAsBool(string $key): bool
    {
        if (func_num_args() === 1) {
            $value = $this->get($key);
        } else {
            $value = $this->get($key);
        }
        $tbr = TypeCaster::toBool($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, 'bool');
        }

        return $tbr;
    }

    /**
     * @return null
     */
    public function getAsNull(string $key)
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
     *
     * @return U
     */
    public function getAsObject(string $key, string $class): object
    {
        $value = $this->get($key);
        $tbr = TypeCaster::toClass($value, $class);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, $class);
        }

        return $tbr;
    }

    /**
     * @template Value
     *
     * @param iterable<Value> $iterable
     *
     * @return CypherMap<Value>
     */
    public static function fromIterable(iterable $iterable): CypherMap
    {
        return new self($iterable);
    }

    /**
     * @return CypherMap<mixed>
     */
    public function getAsCypherMap(string $key): CypherMap
    {
        $value = $this->get($key);
        $tbr = TypeCaster::toCypherMap($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, self::class);
        }

        return $tbr;
    }

    /**
     * @return CypherList<mixed>
     */
    public function getAsCypherList(string $key): CypherList
    {
        $value = $this->get($key);
        $tbr = TypeCaster::toCypherList($value);
        if ($tbr === null) {
            throw new RuntimeTypeException($value, CypherList::class);
        }

        return $tbr;
    }

    public function getAsDate(string $key): Date
    {
        return $this->getAsObject($key, Date::class);
    }

    public function getAsDateTime(string $key): DateTime
    {
        return $this->getAsObject($key, DateTime::class);
    }

    public function getAsDuration(string $key): Duration
    {
        return $this->getAsObject($key, Duration::class);
    }

    public function getAsLocalDateTime(string $key): LocalDateTime
    {
        return $this->getAsObject($key, LocalDateTime::class);
    }

    public function getAsLocalTime(string $key): LocalTime
    {
        return $this->getAsObject($key, LocalTime::class);
    }

    public function getAsTime(string $key): Time
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, Time::class);
        }

        return $this->getAsObject($key, Time::class);
    }

    public function getAsNode(string $key): Node
    {
        return $this->getAsObject($key, Node::class);
    }

    public function getAsRelationship(string $key): Relationship
    {
        return $this->getAsObject($key, Relationship::class);
    }

    public function getAsPath(string $key): Path
    {
        return $this->getAsObject($key, Path::class);
    }

    public function getAsCartesian3DPoint(string $key): Cartesian3DPoint
    {
        return $this->getAsObject($key, Cartesian3DPoint::class);
    }

    public function getAsCartesianPoint(string $key): CartesianPoint
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, CartesianPoint::class);
        }

        return $this->getAsObject($key, CartesianPoint::class);
    }

    public function getAsWGS84Point(string $key): WGS84Point
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, WGS84Point::class);
        }

        return $this->getAsObject($key, WGS84Point::class);
    }

    public function getAsWGS843DPoint(string $key): WGS843DPoint
    {
        if (func_num_args() === 1) {
            return $this->getAsObject($key, WGS843DPoint::class);
        }

        return $this->getAsObject($key, WGS843DPoint::class);
    }

    public function key(): string
    {
        // we have to cast to a string, as the value is potentially an integer if the key is numeric:
        // https://stackoverflow.com/questions/4100488/a-numeric-string-as-array-key-in-php
        return (string) $this->cacheKey();
    }

    /**
     * @return array<string, TValue>
     */
    public function toArray(): array
    {
        $this->preload();

        /** @var array<string, TValue> */
        return $this->cache;
    }

    public function getAsMap(string $string): CypherMap
    {
        return $this->getAsCypherMap($string);
    }

    public function getAsArrayList(string $string): CypherList
    {
        return $this->getAsCypherList($string);
    }

    /**
     * @param callable(TValue, string):bool $callback
     *
     * @return self<TValue>
     *
     * @psalm-mutation-free
     */
    final public function filter(callable $callback): self
    {
        return $this->withOperation(function () use ($callback) {
            foreach ($this as $key => $value) {
                if ($callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * @template ReturnType
     *
     * @param callable(TValue, string):ReturnType $callback
     *
     * @return self<ReturnType>
     *
     * @psalm-suppress ImplementedReturnTypeMismatch
     *
     * @psalm-mutation-free
     */
    final public function map(callable $callback): self
    {
        return $this->withOperation(function () use ($callback) {
            foreach ($this as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * @template TInitial
     *
     * @param TInitial|null                                    $initial
     * @param callable(TInitial|null, TValue, string):TInitial $callback
     *
     * @return TInitial
     */
    final public function reduce(callable $callback, mixed $initial = null): mixed
    {
        foreach ($this as $key => $value) {
            $initial = $callback($initial, $value, $key);
        }

        return $initial;
    }

    /**
     * Iterates over the sequence and applies the callable.
     *
     * @param callable(TValue, string):void $callable
     *
     * @return self<TValue>
     */
    public function each(callable $callable): self
    {
        foreach ($this as $key => $value) {
            $callable($value, $key);
        }

        return $this;
    }
}
