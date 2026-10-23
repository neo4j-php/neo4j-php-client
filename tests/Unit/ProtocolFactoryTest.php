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
use Laudis\Neo4j\Bolt\ProtocolFactory;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ProtocolFactoryTest extends TestCase
{
    public function testOfferedProtocolVersionsCoverBolt6DownTo44(): void
    {
        self::assertSame(['6.1.1', '5.4.4', '4.4'], (new ProtocolFactory())->offeredProtocolVersions());
        self::assertSame(
            ['6.1', '6', '5.4', '5.3', '5.2', '5.1', '5', '4.4'],
            $this->expandOffers((new ProtocolFactory())->offeredProtocolVersions())
        );
    }
    public function testCreateProtocolRejectsBolt61(): void
    {
        $connection = $this->createMock(IConnection::class);
        $connection->method('connect')->willReturn(true);
        // Handshake response for Bolt 6.1: 00 00 01 06
        $connection->method('read')->willReturn("\x00\x00\x01\x06");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Client only supports bolt version 4.4 to 5.4');

        (new ProtocolFactory())->createProtocol($connection);
    }
    /**
     * @param list<string> $offers
     *
     * @return list<string>
     */
    private function expandOffers(array $offers): array
    {
        $versions = [];
        foreach ($offers as $offer) {
            $parts = array_reverse(explode('.', $offer));
            while (count($parts) < 4) {
                array_unshift($parts, '0');
            }

            $range = (int) $parts[1];
            $minor = (int) $parts[2];
            $major = (int) $parts[3];
            for ($step = 0; $step <= $range; ++$step) {
                $offeredMinor = $minor - $step;
                $versions[] = $offeredMinor === 0 ? (string) $major : $major.'.'.$offeredMinor;
            }
        }

        return $versions;
    }
}
