<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Tests\Unit;

use ArrayIterator;
use BadMethodCallException;
use function hexdec;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CypherMapTest extends TestCase
{
    private CypherMap $map;

    public function setUp(): void
    {
        parent::setUp();

        $this->map = new CypherMap(['A' => 'x', 'B' => 'y', 'C' => 'z']);
    }

    public function testFromIterableEqual(): void
    {
        $fromIterable = CypherMap::fromIterable($this->map);

        self::assertNotSame($this->map, $fromIterable);
        self::assertEquals($this->map, $fromIterable);
    }

    public function testFromIterableArray(): void
    {
        $fromIterable = CypherMap::fromIterable(['A' => 'x', 'B' => 'y', 'C' => 'z']);

        self::assertNotSame($this->map, $fromIterable);
        self::assertEquals($this->map, $fromIterable);
    }

    public function testFromIterable(): void
    {
        $fromIterable = CypherMap::fromIterable(new ArrayIterator(['A' => 'x', 'B' => 'y', 'C' => 'z']));

        self::assertNotSame($this->map, $fromIterable);
        self::assertEquals($this->map, $fromIterable);
    }

    public function testCount(): void
    {
        self::assertCount(3, $this->map);
    }

    public function testCountEmpty(): void
    {
        self::assertCount(0, new CypherMap());
    }

    public function testCopy(): void
    {
        $copy = $this->map->copy();

        self::assertNotSame($this->map, $copy);
        self::assertEquals($this->map, $copy);
    }

    public function testCopyDepth(): void
    {
        $list = new CypherMap(['A' => new stdClass()]);
        $copy = $list->copy();

        self::assertNotSame($list, $copy);
        self::assertEquals($list, $copy);
        self::assertSame($list['A'], $copy['A']);
    }

    public function testIsEmpty(): void
    {
        self::assertFalse($this->map->isEmpty());
    }

    public function testIsEmptyEmpty(): void
    {
        self::assertTrue((new CypherMap())->isEmpty());
    }

    public function testToArray(): void
    {
        self::assertEquals(['A' => 'x', 'B' => 'y', 'C' => 'z'], $this->map->toArray());
    }

    public function testMerge(): void
    {
        self::assertEquals(new CypherMap(['A' => 'x', 'B' => 'y', 'C' => 'z']), $this->map->merge($this->map));
    }

    public function testMergeDifferent(): void
    {
        $merged = $this->map->merge(['B' => 'yy', 'C' => 'z', 'D' => 'e']);
        self::assertEquals(new CypherMap(['A' => 'x', 'B' => 'yy', 'C' => 'z', 'D' => 'e']), $merged);
    }

    public function testHasKey(): void
    {
        self::assertTrue($this->map->hasKey('A'));
        self::assertTrue($this->map->hasKey('B'));
        self::assertTrue($this->map->hasKey('C'));
        self::assertFalse($this->map->hasKey('E'));
        self::assertFalse($this->map->hasKey('a'));
    }

    public function testFilterPermissive(): void
    {
        $filter = $this->map->filter(static fn () => true);

        self::assertEquals($this->map, $filter);
        self::assertNotSame($this->map, $filter);
    }

    public function testFilterBlock(): void
    {
        $filter = $this->map->filter(static fn () => false);

        self::assertEquals(new CypherMap(), $filter);
    }

    public function testFilterSelective(): void
    {
        $filter = $this->map->filter(static fn (string $i, string $x) => !($i === 'B' || $x === 'z'));

        self::assertEquals(new CypherMap(['A' => 'x']), $filter);
    }

    public function testMap(): void
    {
        $filter = $this->map->map(static fn (string $i, string $x) => $i.':'.$x);

        self::assertEquals(new CypherList(['A:x', 'B:y', 'C:z']), $filter);
    }

    public function testReduce(): void
    {
        $count = $this->map->reduce(static function (?int $initial, int $key, string $value) {
            return ($initial ?? 0) + $key * hexdec($value);
        }, 5);

        self::assertEquals(5 + hexdec('B') + 2 * hexdec('C'), $count);
    }

    public function testFind(): void
    {
        self::assertFalse($this->map->find('X'));
        self::assertEquals(0, $this->map->find('A'));
        self::assertEquals(1, $this->map->find('B'));
        self::assertEquals(2, $this->map->find('C'));
    }

    public function testReversed(): void
    {
        self::assertEquals(new CypherList(['C', 'B', 'A']), $this->map->reversed());
        self::assertEquals(new CypherList(['A', 'B', 'C']), $this->map);
        self::assertEquals(new CypherList(['A', 'B', 'C']), $this->map->reversed()->reversed());
    }

    public function testSliceSingle(): void
    {
        $sliced = $this->map->slice(1, 1);
        self::assertEquals(new CypherList(['B']), $sliced);
    }

    public function testSliceDouble(): void
    {
        $sliced = $this->map->slice(1, 2);
        self::assertEquals(new CypherList(['B', 'C']), $sliced);
    }

    public function testSliceAll(): void
    {
        $sliced = $this->map->slice(0, 3);
        self::assertEquals(new CypherList(['A', 'B', 'C']), $sliced);
    }

    public function testSliceTooMuch(): void
    {
        $sliced = $this->map->slice(0, 5);
        self::assertEquals(new CypherList(['A', 'B', 'C']), $sliced);
    }

    public function testSliceEmpty(): void
    {
        $sliced = $this->map->slice(0, 0);
        self::assertEquals(new CypherList(), $sliced);
    }

    public function testGetValid(): void
    {
        self::assertEquals('A', $this->map->get(0));
        self::assertEquals('B', $this->map->get(1));
        self::assertEquals('C', $this->map->get(2));
    }

    public function testFirst(): void
    {
        self::assertEquals('A', $this->map->first());
    }

    public function testFirstInvalid(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Cannot grab first element of an empty list');
        (new CypherList())->first();
    }

    public function testLast(): void
    {
        self::assertEquals('C', $this->map->last());
    }

    public function testLastInvalid(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Cannot grab last element of an empty list');
        (new CypherList())->last();
    }

    public function testGetInvalid(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Cannot get item in sequence at position: 3');
        $this->map->get(3);
    }

    public function testGetNegative(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Cannot get item in sequence at position: -1');
        $this->map->get(-1);
    }

    public function testIteration(): void
    {
        $counter = 0;
        foreach ($this->map as $key => $item) {
            ++$counter;
            self::assertEquals(['A', 'B', 'C'][$key], $item);
        }
        self::assertEquals(3, $counter);
    }

    public function testIterationEmpty(): void
    {
        $counter = 0;
        foreach ((new CypherList()) as $key => $item) {
            ++$counter;
            self::assertEquals(['A', 'B', 'C'][$key], $item);
        }
        self::assertEquals(0, $counter);
    }

    public function testOffsetSet(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Laudis\Neo4j\Types\CypherList is immutable');

        $this->map[0] = 'a';
    }

    public function testOffsetUnset(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Laudis\Neo4j\Types\CypherList is immutable');

        unset($this->map[0]);
    }

    public function testOffsetGetValid(): void
    {
        self::assertEquals('A', $this->map[0]);
        self::assertEquals('B', $this->map[1]);
        self::assertEquals('C', $this->map[2]);
    }

    public function testOffsetGetInvalid(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Offset: "3" does not exists in object of instance: Laudis\Neo4j\Types\CypherList');
        $this->map[3];
    }

    public function testOffsetGetNegative(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Offset: "-1" does not exists in object of instance: Laudis\Neo4j\Types\CypherList');
        $this->map[-1];
    }

    public function testIssetValid(): void
    {
        self::assertTrue(isset($this->map[0]));
        self::assertTrue(isset($this->map[1]));
        self::assertTrue(isset($this->map[2]));
    }

    public function testIssetInValid(): void
    {
        self::assertFalse(isset($this->map[-1]));
        self::assertFalse(isset($this->map[3]));
    }

    public function testIssetValidNull(): void
    {
        self::assertTrue(isset((new CypherList([null]))[0]));
    }
}
