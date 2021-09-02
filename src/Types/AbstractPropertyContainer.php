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

use BadMethodCallException;
use Laudis\Neo4j\Contracts\HasPropertiesInterface;

abstract class AbstractPropertyContainer extends AbstractCypherContainer implements HasPropertiesInterface
{
    public function getProperties(): CypherMap
    {
        return new CypherMap($this);
    }

    public function __get($name)
    {
        return $this->getProperties()->get($name);
    }

    public function __set($name, $value): void
    {
        throw new BadMethodCallException(static::class.' is immutable');
    }

    public function __isset($name): bool
    {
        return $this->getProperties()->offsetExists($name);
    }
}
