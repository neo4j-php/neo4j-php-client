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

namespace Laudis\Neo4j\Tests\Unit;

use Laudis\Neo4j\Network\Bolt\BoltConfig;
use PHPUnit\Framework\TestCase;

final class BoltInjectionsTest extends TestCase
{
    public function testConstruct(): void
    {
        $injections = BoltConfig::create('test');
        self::assertEquals('test', $injections->getDatabase());
        $injections = new BoltConfig('abc');
        self::assertEquals('abc', $injections->getDatabase());
    }

    public function testWithDatabase(): void
    {
        $injections = new BoltConfig('abc');
        self::assertEquals('test', $injections->withDatabase('test')->getDatabase());
    }

    public function testWithSslContext(): void
    {
        $injections = new BoltConfig('abc', ['passphrase' => 'test']);
        self::assertEquals(['passphrase' => 'test'], $injections->getSslContextOptions());
        self::assertNull(BoltConfig::create()->getSslContextOptions());

        self::assertEquals(['passphrase' => 'x'], $injections->withSslContextOptions(['passphrase' => 'x'])->getSslContextOptions());
        self::assertEquals(
            ['passphrase' => 'y'],
            $injections->withSslContextOptions(static fn () => ['passphrase' => 'y'])->getSslContextOptions()
        );
    }
}
