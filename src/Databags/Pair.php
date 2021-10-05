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
    /** @var TKey */
    private $key;
    /** @var TValue */
    private $value;

    /**
     * @param TKey   $key
     * @param TValue $value
     */
    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

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
