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

namespace Laudis\Neo4j\Enum;

use JsonSerializable;
use Laudis\TypedEnum\TypedEnum;

/**
 * Defines the access mode of a connection.
 *
 * @method static self ROLLBACK()
 * @method static self NONE()
 *
 * @extends TypedEnum<string>
 *
 * @psalm-immutable
 *
 * @psalm-suppress MutableDependency
 */
final class TransactionEffect extends TypedEnum implements JsonSerializable
{
    private const ROLLBACK = 'rollback';
    private const WRITE = 'none';

    public function jsonSerialize(): string
    {
        return $this->getValue();
    }
}
