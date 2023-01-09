<?php

/*
 * This file is part of the Neo4j PHP Client and Driver package.
 *
 * (c) Nagels <https://nagels.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Tests\Integration;

use Cache\IntegrationTests\SimpleCacheTest;
use Laudis\Neo4j\Common\Cache;

class CacheTest extends SimpleCacheTest
{
    public function createSimpleCache(): Cache
    {
        return Cache::getInstance();
    }
}
