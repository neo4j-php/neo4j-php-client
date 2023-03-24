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
 * @method static self READ()
 * @method static self WRITE()
 *
 * @extends TypedEnum<string>
 *
 * @psalm-immutable
 *
 * @psalm-suppress MutableDependency
 */
final class AccessMode extends TypedEnum implements JsonSerializable
{
    private const READ = 'read';
    private const WRITE = 'write';

    public function jsonSerialize(): string
    {
        return $this->getValue();
    }
}
