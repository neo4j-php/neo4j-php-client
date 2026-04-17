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

use Bolt\protocol\v1\structures\DateTimeZoneId as BoltV1DateTimeZoneId;
use Bolt\protocol\v5\structures\DateTimeZoneId as BoltV5DateTimeZoneId;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\Formatter\Specialised\BoltOGMTranslator;
use Laudis\Neo4j\Types\DateTimeZoneId;
use PHPUnit\Framework\TestCase;

final class BoltOGMTranslatorTest extends TestCase
{
    public function testLegacyBoltDateTimeZoneIdDecodesToUtcSeconds(): void
    {
        $utcUnix = 1654595525;
        $offset = 7200;
        $legacyWireSeconds = $utcUnix + $offset;

        $bolt = new BoltV1DateTimeZoneId($legacyWireSeconds, 0, 'Europe/Stockholm');
        $translator = new BoltOGMTranslator();
        $value = $translator->mapValueToType($bolt);
        self::assertInstanceOf(DateTimeZoneId::class, $value);

        self::assertSame($utcUnix, $value->getSeconds());
        self::assertSame(11, (int) $value->toDateTime()->format('G'));
    }

    public function testV5BoltDateTimeZoneIdPassesUtcSecondsThrough(): void
    {
        $utcUnix = 1654595525;
        $bolt = new BoltV5DateTimeZoneId($utcUnix, 0, 'Europe/Stockholm');
        $translator = new BoltOGMTranslator();
        $value = $translator->mapValueToType($bolt);
        self::assertInstanceOf(DateTimeZoneId::class, $value);

        self::assertSame($utcUnix, $value->getSeconds());
    }

    public function testUnknownIanaZoneOnLegacyStructureThrowsNeo4jException(): void
    {
        $bolt = new BoltV1DateTimeZoneId(0, 0, 'Europe/Neo4j');
        $translator = new BoltOGMTranslator();

        try {
            $translator->mapValueToType($bolt);
            self::fail('Expected Neo4jException');
        } catch (Neo4jException $e) {
            self::assertStringContainsString('Europe/Neo4j', $e->getNeo4jMessage() ?? '');
            self::assertSame('Neo.ClientError.Statement.TypeError', $e->getNeo4jCode());
        }
    }

    public function testUnknownIanaZoneOnV5StructureThrowsNeo4jException(): void
    {
        $bolt = new BoltV5DateTimeZoneId(0, 0, 'Europe/Neo4j');
        $translator = new BoltOGMTranslator();

        try {
            $translator->mapValueToType($bolt);
            self::fail('Expected Neo4jException');
        } catch (Neo4jException $e) {
            self::assertStringContainsString('Europe/Neo4j', $e->getNeo4jMessage() ?? '');
        }
    }
}
