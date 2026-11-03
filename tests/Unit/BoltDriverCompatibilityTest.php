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

namespace Laudis\Neo4j\Tests\Unit;

use Bolt\connection\IConnection;
use Bolt\protocol\V5;
use Bolt\protocol\V6_1;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class BoltDriverCompatibilityTest extends TestCase
{
    public function testV5ProtocolUsesTheInstalledConstructor(): void
    {
        $protocol = new V5($this->createMock(IConnection::class));

        self::assertSame('5', $protocol->getVersion());
    }

    public function testSupportedBolt5ProtocolStaysOnPackStream1(): void
    {
        $protocol = new V5($this->createMock(IConnection::class));
        $method = new ReflectionMethod(V5::class, 'getPackStreamVersion');
        $method->setAccessible(true);

        self::assertSame('5', $protocol->getVersion());
        self::assertSame('1', $method->invoke($protocol));
    }

    public function testBolt61IsCoupledToPackStream11(): void
    {
        $protocol = new V6_1($this->createMock(IConnection::class));
        $method = new ReflectionMethod(V6_1::class, 'getPackStreamVersion');
        $method->setAccessible(true);

        self::assertSame('6.1', $protocol->getVersion());
        self::assertSame('1_1', $method->invoke($protocol));
    }
}
