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
 * The possible routing roles.
 *
 * @method static RoutingRoles LEADER()
 * @method static RoutingRoles FOLLOWER()
 * @method static RoutingRoles ROUTE()
 *
 * @extends TypedEnum<list<string>>
 *
 * @psalm-immutable
 *
 * @psalm-suppress MutableDependency
 */
final class RoutingRoles extends TypedEnum implements JsonSerializable
{
    private const LEADER = ['WRITE', 'LEADER'];
    private const FOLLOWER = ['READ', 'FOLLOWER'];
    private const ROUTE = ['ROUTE'];

    /**
     * @psalm-suppress ImpureMethodCall
     */
    public function jsonSerialize(): string
    {
        if ($this === self::LEADER()) {
            return 'LEADER';
        }

        if ($this === self::FOLLOWER()) {
            return 'FOLLOWER';
        }

        return 'ROUTE';
    }
}
