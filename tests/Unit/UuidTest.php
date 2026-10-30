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

use Bolt\packstream\v1_1\Uuid as BoltUuid;
use InvalidArgumentException;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Formatter\Specialised\BoltOGMTranslator;
use Laudis\Neo4j\ParameterHelper;
use Laudis\Neo4j\TestkitBackend\NutkitValueDecoder;
use Laudis\Neo4j\TestkitBackend\Responses\Types\CypherObject;
use Laudis\Neo4j\Types\CypherMap;
use Laudis\Neo4j\Types\Uuid;
use PHPUnit\Framework\TestCase;
use stdClass;

final class UuidTest extends TestCase
{
    private const SAMPLE = '550e8400-e29b-41d4-a716-446655440000';

    public function testRejectsAnInvalidValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Uuid('not-a-uuid');
    }

    public function testMapsABoltUuidOntoTheDriverType(): void
    {
        $mapped = (new BoltOGMTranslator())->mapValueToType(new BoltUuid(self::SAMPLE));

        self::assertInstanceOf(Uuid::class, $mapped);
        self::assertSame(self::SAMPLE, $mapped->getValue());
    }

    public function testPacksAUuidParameterOnBolt61(): void
    {
        $parameter = ParameterHelper::asParameter(new Uuid(self::SAMPLE), ConnectionProtocol::BOLT_V6_1());

        self::assertInstanceOf(BoltUuid::class, $parameter);
        self::assertSame(self::SAMPLE, $parameter->value);
    }

    public function testPacksAUuidNestedInAMap(): void
    {
        $parameters = ParameterHelper::formatParameters([
            'value' => new CypherMap(['key' => new Uuid(self::SAMPLE)]),
        ], ConnectionProtocol::BOLT_V6_1());

        $value = $parameters->get('value');
        self::assertInstanceOf(stdClass::class, $value);
        $nested = get_object_vars($value);
        self::assertTrue(array_key_exists('key', $nested));
        $nestedUuid = $nested['key'];
        self::assertInstanceOf(BoltUuid::class, $nestedUuid);
        self::assertSame(self::SAMPLE, $nestedUuid->value);
    }

    public function testRejectsAUuidParameterBeforeBolt61(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bolt 6.1');
        ParameterHelper::asParameter(new Uuid(self::SAMPLE), ConnectionProtocol::BOLT_V44());
    }

    public function testRoundTripsThroughTheTestKitProtocol(): void
    {
        $encoded = CypherObject::autoDetect(new Uuid(self::SAMPLE))->jsonSerialize();

        self::assertSame([
            'name' => 'CypherUUID',
            'data' => ['value' => self::SAMPLE],
        ], $encoded);

        $decoded = NutkitValueDecoder::decode($encoded);
        self::assertInstanceOf(Uuid::class, $decoded);
        self::assertSame(self::SAMPLE, $decoded->getValue());
    }
}
