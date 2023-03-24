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

namespace Laudis\Neo4j\Tests\Integration;

use Cache\IntegrationTests\SimpleCacheTest;
use Laudis\Neo4j\Common\Cache;

class CacheTest extends SimpleCacheTest
{
    /** @psalm-suppress MissingPropertyType */
    protected $skippedTests = [
        'testGetInvalidKeys' => 'Handled by dynamic typing',
        'testGetMultipleInvalidKeys' => 'Handled by dynamic typing',
        'testGetMultipleNoIterable' => 'Handled by dynamic typing',
        'testSetInvalidKeys' => 'Handled by dynamic typing',
        'testSetMultipleNoIterable' => 'Handled by dynamic typing',
        'testHasInvalidKeys' => 'Handled by dynamic typing',
        'testDeleteInvalidKeys' => 'Handled by dynamic typing',
        'testDeleteMultipleInvalidKeys' => 'Handled by dynamic typing',
        'testDeleteMultipleNoIterable' => 'Handled by dynamic typing',
        'testSetInvalidTtl' => 'Handled by dynamic typing',
        'testSetMultipleInvalidTtl' => 'Handled by dynamic typing',
    ];

    public function createSimpleCache(): Cache
    {
        return Cache::getInstance();
    }
}
