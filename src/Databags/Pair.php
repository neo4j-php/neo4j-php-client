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

namespace Laudis\Neo4j\Databags;

/**
 * A basic Key value Pair.
 *
 * @template TKey
 * @template TValue
 *
 * @psalm-immutable
 */
final class Pair
{
    /**
     * @param TKey   $key
     * @param TValue $value
     */
    public function __construct(
        private $key,
        private $value
    ) {}

    /**
     * @return TKey
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return TValue
     */
    public function getValue()
    {
        return $this->value;
    }
}
