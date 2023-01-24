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

namespace Laudis\Neo4j\Common;

use function array_key_exists;

use DateInterval;
use DateTimeImmutable;
use Generator;

use function is_int;
use function is_iterable;
use function is_object;
use function is_string;

use Laudis\Neo4j\Exception\InvalidCacheArgumentException;

use const PHP_INT_MAX;

use Psr\SimpleCache\CacheInterface;

use function str_contains;
use function time;

/**
 * Basic Cache implementation based on an array.
 *
 * This cache does not persist throughout sessions and is only here to provide a default. If you want to use the
 * cache as data persistence you will have to provide your own implementation.
 *
 * @see https://packagist.org/providers/psr/simple-cache-implementation For existing implementations.
 *
 * @template T
 *
 * @psalm-suppress MoreSpecificImplementedParamType
 * @psalm-suppress DocblockTypeContradiction
 * @psalm-suppress RedundantConditionGivenDocblockType
 */
class Cache implements CacheInterface
{
    /** @var array<string, array{0: T, 1: int}> */
    private array $items = [];
    private static ?self $instance = null;

    private function __construct() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @template U
     *
     * @param string $key
     * @param U      $default
     *
     * @return T|U
     */
    public function get($key, $default = null)
    {
        $this->assertValidKey($key);

        return $this->getNoAssert($key, $default);
    }

    public function has($key): bool
    {
        $this->assertValidKey($key);

        return array_key_exists($key, $this->items) && $this->items[$key][1] > time();
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    /**
     * @param iterable<string> $keys
     *
     * @throws InvalidCacheArgumentException
     */
    public function deleteMultiple($keys): bool
    {
        $this->assertIterable($keys);

        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    /**
     * @param string                $key
     * @param T                     $value
     * @param int|DateInterval|null $ttl
     */
    public function set($key, $value, $ttl = null): bool
    {
        $this->assertValidKey($key);

        if ($ttl instanceof DateInterval) {
            $ttl = (new DateTimeImmutable())->add($ttl)->getTimestamp();
        } elseif ($ttl === null) {
            $ttl = PHP_INT_MAX;
        } elseif (is_int($ttl)) {
            $ttl += time();
        } else {
            throw new InvalidCacheArgumentException();
        }

        if (is_object($value)) {
            $value = clone $value;
        }

        $this->items[$key] = [$value, $ttl];

        return true;
    }

    public function delete($key): bool
    {
        $this->assertValidKey($key);

        unset($this->items[$key]);

        return true;
    }

    /**
     * @template U
     *
     * @param iterable<string> $keys
     * @param U                $default
     *
     * @return Generator<string, T|U>
     */
    public function getMultiple($keys, $default = null): Generator
    {
        $this->assertIterable($keys);

        /** @var list<string> $cachedKeys */
        $cachedKeys = [];
        foreach ($keys as $key) {
            $this->assertValidKey($key); // pre-check the keys to make sure they are valid as the generator will be delay the tests
            $cachedKeys[] = $key;
        }

        return (function () use ($cachedKeys, $default) {
            foreach ($cachedKeys as $key) {
                yield $key => $this->getNoAssert($key, $default);
            }
        })();
    }

    /**
     * @param iterable<string, T>   $values
     * @param int|DateInterval|null $ttl
     *
     * @throws InvalidCacheArgumentException
     */
    public function setMultiple($values, $ttl = null): bool
    {
        $this->assertIterable($values);

        foreach ($values as $key => $value) {
            if (is_int($key)) {
                $key = (string) $key;
            }
            $this->set($key, $value, $ttl);
        }

        return true;
    }

    /**
     * @param-out string $key
     *
     * @throws InvalidCacheArgumentException
     */
    private function assertValidKey(mixed $key): void
    {
        if (is_string($key)) {
            if ($key === '' ||
                str_contains($key, '{') ||
                str_contains($key, '}') ||
                str_contains($key, '(') ||
                str_contains($key, ')') ||
                str_contains($key, '/') ||
                str_contains($key, '\\') ||
                str_contains($key, '@') ||
                str_contains($key, ':')
            ) {
                throw new InvalidCacheArgumentException();
            }
        } else {
            throw new InvalidCacheArgumentException();
        }
    }

    /**
     * @param-out iterable<string> $keys
     *
     * @throws InvalidCacheArgumentException
     */
    private function assertIterable(mixed $keys): void
    {
        if (!is_iterable($keys)) {
            throw new InvalidCacheArgumentException();
        }
    }

    /**
     * @template U
     *
     * @param U $default
     *
     * @return T|U
     */
    private function getNoAssert(string $key, $default)
    {
        if (array_key_exists($key, $this->items) && $this->items[$key][1] > time()) {
            return $this->items[$key][0];
        }

        return $default;
    }
}
