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
 * Defines the socket type to use for connections.
 *
 * @method static self SOCKETS()
 * @method static self STREAM()
 *
 * @extends TypedEnum<string>
 *
 * @psalm-immutable
 *
 * @psalm-suppress MutableDependency
 */
final class SocketType extends TypedEnum implements JsonSerializable
{
    private const SOCKETS = 'sockets';
    private const STREAM = 'stream';

    public function jsonSerialize(): string
    {
        return $this->getValue();
    }
}
