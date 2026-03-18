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

use ArrayIterator;
use Generator;
use Laudis\Neo4j\TypeCaster;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

final class TypeCasterTest extends TestCase
{
    private stdClass $stdObj;

    private object $stringable;

    public function setUp(): void
    {
        parent::setUp();

        $this->stdObj = new stdClass();
        $this->stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringable-value';
            }
        };
    }

    public function testToStringWithNull(): void
    {
        self::assertSame('', TypeCaster::toString(null));
    }

    public function testToStringWithInt(): void
    {
        self::assertSame('42', TypeCaster::toString(42));
    }

    public function testToStringWithFloat(): void
    {
        self::assertSame('3.14', TypeCaster::toString(3.14));
    }

    public function testToStringWithBoolTrue(): void
    {
        self::assertSame('1', TypeCaster::toString(true));
    }

    public function testToStringWithBoolFalse(): void
    {
        self::assertSame('', TypeCaster::toString(false));
    }

    public function testToStringWithString(): void
    {
        self::assertSame('hello', TypeCaster::toString('hello'));
    }

    public function testToStringWithStringEmpty(): void
    {
        self::assertSame('', TypeCaster::toString(''));
    }

    public function testToStringWithStringable(): void
    {
        self::assertSame('stringable-value', TypeCaster::toString($this->stringable));
    }

    public function testToStringWithArrayReturnsNull(): void
    {
        self::assertNull(TypeCaster::toString([1, 2, 3]));
    }

    public function testToStringWithObjectReturnsNull(): void
    {
        self::assertNull(TypeCaster::toString($this->stdObj));
    }

    public function testToNull(): void
    {
        self::assertNull(TypeCaster::toNull());
    }

    public function testToIntWithNull(): void
    {
        self::assertNull(TypeCaster::toInt(null));
    }

    public function testToIntWithInt(): void
    {
        self::assertSame(42, TypeCaster::toInt(42));
    }

    public function testToIntWithFloat(): void
    {
        self::assertSame(3, TypeCaster::toInt(3.14));
    }

    public function testToIntWithStringNumeric(): void
    {
        self::assertSame(123, TypeCaster::toInt('123'));
    }

    public function testToIntWithBoolTrue(): void
    {
        self::assertSame(1, TypeCaster::toInt(true));
    }

    public function testToIntWithBoolFalse(): void
    {
        self::assertSame(0, TypeCaster::toInt(false));
    }

    public function testToIntWithStringableNumeric(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return '42';
            }
        };
        self::assertSame(42, TypeCaster::toInt($stringable));
    }

    public function testToIntWithStringNonNumericReturnsNull(): void
    {
        self::assertNull(TypeCaster::toInt('abc'));
    }

    public function testToIntWithArrayReturnsNull(): void
    {
        self::assertNull(TypeCaster::toInt([1, 2, 3]));
    }

    public function testToFloatWithNull(): void
    {
        self::assertNull(TypeCaster::toFloat(null));
    }

    public function testToFloatWithInt(): void
    {
        self::assertSame(42.0, TypeCaster::toFloat(42));
    }

    public function testToFloatWithFloat(): void
    {
        self::assertSame(3.14, TypeCaster::toFloat(3.14));
    }

    public function testToFloatWithStringNumeric(): void
    {
        self::assertSame(99.9, TypeCaster::toFloat('99.9'));
    }

    public function testToFloatWithBoolTrue(): void
    {
        self::assertSame(1.0, TypeCaster::toFloat(true));
    }

    public function testToFloatWithBoolFalse(): void
    {
        self::assertSame(0.0, TypeCaster::toFloat(false));
    }

    public function testToFloatWithStringableNumeric(): void
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return '42.5';
            }
        };
        self::assertSame(42.5, TypeCaster::toFloat($stringable));
    }

    public function testToFloatWithStringNonNumericReturnsNull(): void
    {
        self::assertNull(TypeCaster::toFloat('abc'));
    }

    public function testToFloatWithArrayReturnsNull(): void
    {
        self::assertNull(TypeCaster::toFloat([1, 2, 3]));
    }

    public function testToBoolWithNull(): void
    {
        self::assertNull(TypeCaster::toBool(null));
    }

    public function testToBoolWithTrue(): void
    {
        self::assertTrue(TypeCaster::toBool(true));
    }

    public function testToBoolWithFalse(): void
    {
        self::assertFalse(TypeCaster::toBool(false));
    }

    public function testToBoolWithIntOne(): void
    {
        self::assertTrue(TypeCaster::toBool(1));
    }

    public function testToBoolWithIntZero(): void
    {
        self::assertFalse(TypeCaster::toBool(0));
    }

    public function testToBoolWithStringOne(): void
    {
        self::assertTrue(TypeCaster::toBool('1'));
    }

    public function testToBoolWithStringZero(): void
    {
        self::assertFalse(TypeCaster::toBool('0'));
    }

    public function testToBoolWithStringNonNumericReturnsNull(): void
    {
        self::assertNull(TypeCaster::toBool('true'));
    }

    public function testToBoolWithArrayReturnsNull(): void
    {
        self::assertNull(TypeCaster::toBool([1, 2, 3]));
    }

    public function testToClassWithMatchingClass(): void
    {
        self::assertSame($this->stdObj, TypeCaster::toClass($this->stdObj, stdClass::class));
    }

    public function testToClassWithWrongClass(): void
    {
        self::assertNull(TypeCaster::toClass($this->stdObj, Stringable::class));
    }

    public function testToClassWithStringableInstance(): void
    {
        self::assertSame($this->stringable, TypeCaster::toClass($this->stringable, Stringable::class));
    }

    public function testToClassWithStringReturnsNull(): void
    {
        self::assertNull(TypeCaster::toClass('hello', stdClass::class));
    }

    public function testToClassWithIntReturnsNull(): void
    {
        self::assertNull(TypeCaster::toClass(42, stdClass::class));
    }

    public function testToClassWithNullReturnsNull(): void
    {
        self::assertNull(TypeCaster::toClass(null, stdClass::class));
    }

    public function testToClassWithArrayReturnsNull(): void
    {
        self::assertNull(TypeCaster::toClass([], stdClass::class));
    }

    public function testToArrayWithArray(): void
    {
        self::assertEquals([1, 2, 3], TypeCaster::toArray([1, 2, 3]));
    }

    public function testToArrayWithAssociativeArray(): void
    {
        self::assertEquals([1, 2], TypeCaster::toArray(['a' => 1, 'b' => 2]));
    }

    public function testToArrayWithEmptyArray(): void
    {
        self::assertEquals([], TypeCaster::toArray([]));
    }

    public function testToArrayWithIterator(): void
    {
        self::assertEquals([10, 20], TypeCaster::toArray(new ArrayIterator([10, 20])));
    }

    public function testToArrayWithCypherList(): void
    {
        self::assertEquals([1, 2, 3], TypeCaster::toArray(new CypherList([1, 2, 3])));
    }

    public function testToArrayWithCypherMap(): void
    {
        self::assertEquals([1, 2], TypeCaster::toArray(new CypherMap(['x' => 1, 'y' => 2])));
    }

    public function testToArrayWithGenerator(): void
    {
        $gen = (static function (): Generator {
            yield 1;
            yield 2;
        })();
        self::assertEquals([1, 2], TypeCaster::toArray($gen));
    }

    public function testToArrayWithNullReturnsNull(): void
    {
        self::assertNull(TypeCaster::toArray(null));
    }

    public function testToArrayWithIntReturnsNull(): void
    {
        self::assertNull(TypeCaster::toArray(42));
    }

    public function testToArrayWithStringReturnsNull(): void
    {
        self::assertNull(TypeCaster::toArray('hello'));
    }

    public function testToArrayWithObjectReturnsNull(): void
    {
        self::assertNull(TypeCaster::toArray($this->stdObj));
    }

    public function testToCypherListWithArray(): void
    {
        $result = TypeCaster::toCypherList([1, 2, 3]);
        self::assertInstanceOf(CypherList::class, $result);
        self::assertEquals([1, 2, 3], $result->toArray());
    }

    public function testToCypherListWithEmptyArray(): void
    {
        $result = TypeCaster::toCypherList([]);
        self::assertInstanceOf(CypherList::class, $result);
        self::assertEquals([], $result->toArray());
    }

    public function testToCypherListWithIterator(): void
    {
        $result = TypeCaster::toCypherList(new ArrayIterator([10, 20]));
        self::assertInstanceOf(CypherList::class, $result);
        self::assertEquals([10, 20], $result->toArray());
    }

    public function testToCypherListWithCypherList(): void
    {
        $list = new CypherList([1, 2]);
        $result = TypeCaster::toCypherList($list);
        self::assertInstanceOf(CypherList::class, $result);
        self::assertEquals([1, 2], $result->toArray());
    }

    public function testToCypherListWithCypherMap(): void
    {
        $result = TypeCaster::toCypherList(new CypherMap(['x' => 1]));
        self::assertInstanceOf(CypherList::class, $result);
        self::assertEquals([1], $result->toArray());
    }

    public function testToCypherListWithNullReturnsNull(): void
    {
        self::assertNull(TypeCaster::toCypherList(null));
    }

    public function testToCypherListWithIntReturnsNull(): void
    {
        self::assertNull(TypeCaster::toCypherList(42));
    }

    public function testToCypherListWithStringReturnsNull(): void
    {
        self::assertNull(TypeCaster::toCypherList('hello'));
    }

    public function testToCypherListWithObjectReturnsNull(): void
    {
        self::assertNull(TypeCaster::toCypherList($this->stdObj));
    }

    public function testToCypherMapWithArray(): void
    {
        $result = TypeCaster::toCypherMap(['a' => 1, 'b' => 2]);
        self::assertInstanceOf(CypherMap::class, $result);
        self::assertEquals(['a' => 1, 'b' => 2], $result->toArray());
    }

    public function testToCypherMapWithEmptyArray(): void
    {
        $result = TypeCaster::toCypherMap([]);
        self::assertInstanceOf(CypherMap::class, $result);
        self::assertEquals([], $result->toArray());
    }

    public function testToCypherMapWithIterator(): void
    {
        $result = TypeCaster::toCypherMap(new ArrayIterator(['x' => 10, 'y' => 20]));
        self::assertInstanceOf(CypherMap::class, $result);
        self::assertEquals(['x' => 10, 'y' => 20], $result->toArray());
    }

    public function testToCypherMapWithCypherMap(): void
    {
        $map = new CypherMap(['k' => 'v']);
        $result = TypeCaster::toCypherMap($map);
        self::assertInstanceOf(CypherMap::class, $result);
        self::assertEquals(['k' => 'v'], $result->toArray());
    }

    public function testToCypherMapWithCypherList(): void
    {
        $result = TypeCaster::toCypherMap(new CypherList([1, 2]));
        self::assertInstanceOf(CypherMap::class, $result);
        self::assertEquals(['0' => 1, '1' => 2], $result->toArray());
    }

    public function testToCypherMapWithNullReturnsNull(): void
    {
        self::assertNull(TypeCaster::toCypherMap(null));
    }

    public function testToCypherMapWithIntReturnsNull(): void
    {
        self::assertNull(TypeCaster::toCypherMap(42));
    }

    public function testToCypherMapWithStringReturnsNull(): void
    {
        self::assertNull(TypeCaster::toCypherMap('hello'));
    }

    public function testToCypherMapWithObjectReturnsNull(): void
    {
        self::assertNull(TypeCaster::toCypherMap($this->stdObj));
    }
}
