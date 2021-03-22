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

use Laudis\Neo4j\Network\Bolt\BoltInjections;
use PHPUnit\Framework\TestCase;

final class BoltInjectionsTest extends TestCase
{
    public function testConstruct(): void
    {
        $injections = BoltInjections::create('test');
        self::assertEquals('test', $injections->database());
        $injections = new BoltInjections('abc');
        self::assertEquals('abc', $injections->database());
    }

    public function testSystem(): void
    {
        $injections = BoltInjections::create('system');
        self::assertEquals('system', $injections->database());
    }

    public function testWithDatabase(): void
    {
        $injections = new BoltInjections('abc');
        self::assertEquals('test', $injections->withDatabase('test')->database());
    }

    public function testWithSslContext(): void
    {
        $injections = new BoltInjections('abc', ['passphrase' => 'test']);
        self::assertEquals(['passphrase' => 'test'], $injections->sslContextOptions());
        self::assertNull(BoltInjections::create()->sslContextOptions());

        self::assertEquals(['passphrase' => 'x'], $injections->withSslContextOptions(['passphrase' => 'x'])->sslContextOptions());
        self::assertEquals(
            ['passphrase' => 'y'],
            $injections->withSslContextOptions(static fn () => ['passphrase' => 'y'])->sslContextOptions()
        );
    }
}
