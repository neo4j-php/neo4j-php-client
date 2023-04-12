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
        'testGetInvalidKeys' => 'Handled by strict dynamic typing',
        'testGetMultipleInvalidKeys' => 'Handled by strict dynamic typing',
        'testGetMultipleNoIterable' => 'Handled by strict dynamic typing',
        'testSetInvalidKeys' => 'Handled by strict dynamic typing',
        'testSetMultipleNoIterable' => 'Handled by strict dynamic typing',
        'testHasInvalidKeys' => 'Handled by strict dynamic typing',
        'testDeleteInvalidKeys' => 'Handled by strict dynamic typing',
        'testDeleteMultipleInvalidKeys' => 'Handled by strict dynamic typing',
        'testDeleteMultipleNoIterable' => 'Handled by strict dynamic typing',
        'testSetInvalidTtl' => 'Handled by strict dynamic typing',
        'testSetMultipleInvalidTtl' => 'Handled by strict dynamic typing',
    ];

    public function createSimpleCache(): Cache
    {
        return Cache::getInstance();
    }
}
