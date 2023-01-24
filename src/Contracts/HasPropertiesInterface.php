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

namespace Laudis\Neo4j\Contracts;

use BadMethodCallException;
use Laudis\Neo4j\Types\CypherMap;

/**
 * Defines how an object with properties should behave.
 *
 * @psalm-immutable
 *
 * @template T
 */
interface HasPropertiesInterface
{
    /**
     * Returns the properties a map.
     *
     * @return CypherMap<T>
     */
    public function getProperties(): CypherMap;

    /**
     * @param string $name
     *
     * @return T
     */
    public function __get($name);

    /**
     * Always throws an exception as cypher objects are immutable.
     *
     * @param string $name
     * @param T      $value
     *
     * @throws BadMethodCallException
     */
    public function __set($name, $value): void;

    /**
     * Checks to see if the property exists and is set.
     *
     * @param string $name
     */
    public function __isset($name): bool;
}
