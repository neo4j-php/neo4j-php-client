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

use Bolt\protocol\IStructure;
use Laudis\Neo4j\Contracts\BoltConvertibleInterface;

/**
 * Neo4j Vector type (e.g. embedding). Holds a list of numbers.
 *
 * @psalm-immutable
 */
final class Vector implements BoltConvertibleInterface
{
    /**
     * @param list<int|float> $values
     */
    public function __construct(
        private readonly array $values,
    ) {
    }

    /**
     * @return list<int|float>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * @return list<int|float>
     */
    public function toArray(): array
    {
        return $this->values;
    }

    public function convertToBolt(): IStructure
    {
        /** @psalm-suppress ImpureMethodCall Bolt Vector::encode() is not annotated pure in vendor */
        return \Bolt\protocol\v6\structures\Vector::encode($this->values);
    }
}
