<?php

namespace Laudis\Neo4j\Tests\Integration;

use Cache\IntegrationTests\SimpleCacheTest;
use Laudis\Neo4j\Common\Cache;

class CacheTest extends SimpleCacheTest
{
    public function createSimpleCache(): Cache
    {
        return new Cache();
    }
}
