<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j;

use Laudis\TypedEnum\TypedEnum;

/**
 * @method static Protocol HTTP()
 * @method static Protocol BOLT()
 */
final class Protocol extends TypedEnum
{
    private const HTTP = 'http';
    private const BOLT = 'bolt';
}
