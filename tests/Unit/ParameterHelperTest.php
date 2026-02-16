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

use Bolt\protocol\v1\structures\DateTimeZoneId;
use Bolt\protocol\v6\structures\Vector as BoltVector;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;
use Iterator;
use Laudis\Neo4j\Enum\ConnectionProtocol;
use Laudis\Neo4j\Enum\VectorTypeMarker;
use Laudis\Neo4j\ParameterHelper;
use Laudis\Neo4j\Types\Vector as DriverVector;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

final class ParameterHelperTest extends TestCase
{
    /** @var iterable<iterable|scalar|null> */
    private static iterable $invalidIterable;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        /**
         * @psalm-suppress MixedPropertyTypeCoercion
         * @psalm-suppress MissingTemplateParam
         */
        self::$invalidIterable = new class implements Iterator {
            private bool $initial = true;

            public function current(): int
            {
                return 1;
            }

            public function next(): void
            {
                $this->initial = false;
            }

            public function key(): stdClass
            {
                return new stdClass();
            }

            public function valid(): bool
            {
                return $this->initial;
            }

            public function rewind(): void
            {
                $this->initial = true;
            }
        };
    }

    public function testAsList(): void
    {
        self::assertEquals([1, 2, 3], ParameterHelper::asList([2 => 1, 'a' => 2, 'd' => 3])->toArray());
    }

    public function testAsMap(): void
    {
        self::assertEquals([2 => 1, 'a' => 2, 'd' => 3], ParameterHelper::asMap([2 => 1, 'a' => 2, 'd' => 3])->toArray());
    }

    public function testFormatParameterString(): void
    {
        self::assertEquals(['a' => 'b', 'c' => 'd'], ParameterHelper::formatParameters([
            'a' => 'b',
            'c' => 'd',
        ], ConnectionProtocol::BOLT_V44())->toArray());
    }

    public function testFormatParameterInteger(): void
    {
        self::assertEquals([2 => 'b', 3 => 'd'], ParameterHelper::formatParameters([
            2 => 'b',
            3 => 'd',
        ], ConnectionProtocol::BOLT_V44())->toArray());
    }

    public function testFormatParameterVector(): void
    {
        self::assertEquals(['b', 'd'], ParameterHelper::formatParameters([
            'b',
            'd',
        ], ConnectionProtocol::BOLT_V44())->toArray());
    }

    public function testFormatParameterIterable(): void
    {
        self::assertEquals([[1, 2]], ParameterHelper::formatParameters([
            [1, 2],
        ], ConnectionProtocol::BOLT_V44())->toArray());
    }

    public function testFormatParameterInvalidIterable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @psalm-suppress MixedArgumentTypeCoercion */
        ParameterHelper::formatParameters(self::$invalidIterable, ConnectionProtocol::BOLT_V44());
    }

    public function testFormatParameterInvalidIterable2(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ParameterHelper::formatParameters([
            'a' => [
                self::$invalidIterable,
            ],
        ], ConnectionProtocol::BOLT_V44());
    }

    public function testAsParameterEmptyVector(): void
    {
        $result = ParameterHelper::asParameter([], ConnectionProtocol::BOLT_V44());
        self::assertIsArray($result);
        self::assertCount(0, $result);
    }

    public function testAsParameterEmptyMap(): void
    {
        $result = ParameterHelper::asParameter([], ConnectionProtocol::BOLT_V44());
        self::assertIsArray($result);
    }

    public function testAsParameterEmptyArray(): void
    {
        $result = ParameterHelper::asParameter([], ConnectionProtocol::BOLT_V44());
        self::assertIsArray($result);
    }

    public function testStringable(): void
    {
        $result = ParameterHelper::asParameter(new class implements Stringable {
            public function __toString(): string
            {
                return 'abc';
            }
        }, ConnectionProtocol::BOLT_V44());
        self::assertEquals('abc', $result);
    }

    public function testInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot format parameter of type: stdClass to work with Neo4J');
        ParameterHelper::asParameter(new stdClass(), ConnectionProtocol::BOLT_V44());
    }

    public function testDateTime(): void
    {
        $date = ParameterHelper::asParameter(new DateTime('now', new DateTimeZone('Europe/Brussels')), ConnectionProtocol::BOLT_V44());

        self::assertInstanceOf(DateTimeZoneId::class, $date);
        self::assertEquals('Europe/Brussels', $date->tz_id);
    }

    public function testDateTime5(): void
    {
        $date = ParameterHelper::asParameter(new DateTime('now', new DateTimeZone('Europe/Brussels')), ConnectionProtocol::BOLT_V5());

        self::assertInstanceOf(\Bolt\protocol\v5\structures\DateTimeZoneId::class, $date);
    }

    public function testAsVectorReturnsDriverVector(): void
    {
        $vec = [1, 2, 3];
        $result = ParameterHelper::asVector($vec);

        self::assertInstanceOf(DriverVector::class, $result);
        self::assertEquals($vec, $result->getValues());
        self::assertNull($result->getTypeMarker());
    }

    public function testAsVectorWithTypeMarker(): void
    {
        $vec = [1.0, 2.0, 3.0];
        $result = ParameterHelper::asVector($vec, VectorTypeMarker::FLOAT_32);

        self::assertInstanceOf(DriverVector::class, $result);
        self::assertEquals($vec, $result->getValues());
        self::assertSame(VectorTypeMarker::FLOAT_32, $result->getTypeMarker());
        $bolt = $result->convertToBolt();
        self::assertInstanceOf(BoltVector::class, $bolt);
        self::assertEqualsWithDelta($vec, $bolt->decode(), 0.0001);
    }

    public function testAsParameterConvertsDriverVectorToBolt(): void
    {
        $vector = ParameterHelper::asVector([1, 2, 3]);
        $result = ParameterHelper::asParameter($vector, ConnectionProtocol::BOLT_V44());

        self::assertInstanceOf(BoltVector::class, $result);
        self::assertEquals([1, 2, 3], $result->decode());
    }

    public function testFormatParametersConvertsDriverVectorToBolt(): void
    {
        $vector = ParameterHelper::asVector([1, 2]);
        $formatted = ParameterHelper::formatParameters(['embedding' => $vector], ConnectionProtocol::BOLT_V44());

        self::assertCount(1, $formatted);
        $value = $formatted->get('embedding');
        self::assertInstanceOf(BoltVector::class, $value);
        self::assertEquals([1, 2], $value->decode());
    }
}
