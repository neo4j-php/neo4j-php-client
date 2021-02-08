<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Enum;

use Laudis\TypedEnum\TypedEnum;

/**
 * @method static RoutingRoles LEADER()
 * @method static RoutingRoles FOLLOWER()
 * @method static RoutingRoles ROUTE()
 *
 * @extends TypedEnum<string>
 */
final class RoutingRoles extends TypedEnum
{
    private const LEADER = 'WRITE';
    private const FOLLOWER = 'READ';
    private const ROUTE = 'ROUTE';
}
