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

use BadMethodCallException;
use Laudis\Neo4j\Contracts\HasPropertiesInterface;

use function sprintf;

/**
 * @psalm-import-type OGMTypes from \Laudis\Neo4j\Formatter\OGMFormatter
 *
 * @template PropertyTypes
 * @template ObjectTypes
 *
 * @extends AbstractCypherObject<string, ObjectTypes>
 *
 * @implements HasPropertiesInterface<PropertyTypes>
 *
 * @psalm-immutable
 */
abstract class AbstractPropertyObject extends AbstractCypherObject implements HasPropertiesInterface
{
    public function __get($name)
    {
        /** @psalm-suppress ImpureMethodCall */
        return $this->getProperties()->get($name);
    }

    public function __set($name, $value): void
    {
        throw new BadMethodCallException(sprintf('%s is immutable', static::class));
    }

    public function __isset($name): bool
    {
        /** @psalm-suppress ImpureMethodCall */
        return $this->getProperties()->offsetExists($name);
    }
}
