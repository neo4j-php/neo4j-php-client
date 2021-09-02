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

namespace Laudis\Neo4j\Contracts;

use ArrayAccess;
use IteratorAggregate;
use JsonSerializable;

/**
 * @template TKey
 * @template TValue
 *
 * @template-extends ArrayAccess<TKey, TValue>
 * @template-extends IteratorAggregate<TKey, TValue>
 */
interface CypherContainerInterface extends JsonSerializable, ArrayAccess, IteratorAggregate
{
    /**
     * @return array<TKey, TValue>
     */
    public function jsonSerialize(): array;
}
