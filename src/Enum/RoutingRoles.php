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

namespace Laudis\Neo4j\Enum;

use const E_DEPRECATED;
use function error_reporting;
use JsonSerializable;
use Laudis\TypedEnum\TypedEnum;

/**
 * Turn of error reporting for class definition. PHP Users of 8.1 receive a deprectation warning otherwise but
 * it is not fixable from the minimum version 7.4 as it required the "mixed" keyword.
 */
$oldReporting = error_reporting(error_reporting() & (~E_DEPRECATED));

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

/**
 * Turn back on old error reporting after class definition.
 */
error_reporting($oldReporting);
