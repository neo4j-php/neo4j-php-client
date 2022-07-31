<?php

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Common;

use function array_diff_key;
use function array_key_exists;
use function array_merge;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use SplQueue;

/**
 * Basic Cache implementation based on an array.
 *
 * This cache does not persist throughout sessions and is only here to provide a default. If you want to use the
 * cache as data persistence you will have to provide your own implementation.
 *
 * Please refer to https://packagist.org/providers/psr/cache-implementation for existing implementations.
 *
 * @template T of CacheItemInterface
 *
 * @psalm-suppress MoreSpecificImplementedParamType
 */
class Cache implements CacheItemPoolInterface
{
    /** @var array<string, CacheItemInterface> */
    private array $items = [];
    private SplQueue $keyPriorities;
    private array $toSave = [];

    public function __construct()
    {
        $this->keyPriorities = new SplQueue();
    }

    private function cleanItems(): void
    {
        while ($this->keyPriorities->count() > 10000) {
            $key = $this->keyPriorities->dequeue();
            unset($this->items[$key]);
        }
    }

    public function getItem(string $key): CacheItemInterface
    {
        $this->cleanItems();

        if (array_key_exists($key, $this->items)) {
            return $this->items[$key];
        }

        return $this->defaultEmptyValue($key);
    }

    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->getItem($key);
        }
    }

    public function hasItem(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        $this->items = array_diff_key($this->items, array_flip($keys));

        return true;
    }

    /**
     * @param T $item
     */
    public function save(CacheItemInterface $item): bool
    {
        $this->items[$item->getKey()] = clone $item;

        return true;
    }

    /**
     * @param T $item
     */
    public function saveDeferred(CacheItemInterface $item): bool
    {
        $this->toSave[$item->getKey()] = $item;

        return true;
    }

    public function commit(): bool
    {
        $this->items = array_merge($this->items, $this->toSave);
        $this->toSave = [];

        return true;
    }

    private function defaultEmptyValue(string $key): CacheItemInterface
    {
        return new CacheItem($key, null, false);
    }
}
