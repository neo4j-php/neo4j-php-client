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
use function json_encode;
use Laudis\Neo4j\Types\CypherList;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CypherListTest extends TestCase
{
    /** @var CypherList<string> */
    private CypherList $list;

    public function setUp(): void
    {
        parent::setUp();

        $this->list = new CypherList(['A', 'B', 'C']);
    }

    public function testFromIterableEqual(): void
    {
        $fromIterable = CypherList::fromIterable($this->list);

        self::assertNotSame($this->list, $fromIterable);
        self::assertEquals($this->list, $fromIterable);
    }

    public function testFromIterableArray(): void
    {
        $fromIterable = CypherList::fromIterable(['A', 'B', 'C']);

        self::assertNotSame($this->list, $fromIterable);
        self::assertEquals($this->list, $fromIterable);
    }

    public function testFromIterable(): void
    {
        $fromIterable = CypherList::fromIterable(new ArrayIterator(['A', 'B', 'C']));

        self::assertNotSame($this->list, $fromIterable);
        self::assertEquals($this->list, $fromIterable);
    }

    public function testCount(): void
    {
        self::assertCount(3, $this->list);
    }

    public function testCountEmpty(): void
    {
        self::assertCount(0, new CypherList());
    }

    public function testCopy(): void
    {
        $copy = $this->list->copy();

        self::assertNotSame($this->list, $copy);
        self::assertEquals($this->list, $copy);
    }

    public function testCopyDepth(): void
    {
        $list = new CypherList([new stdClass()]);
        $copy = $list->copy();

        self::assertNotSame($list, $copy);
        self::assertEquals($list, $copy);
        self::assertSame($list[0], $copy[0]);
    }

    public function testIsEmpty(): void
    {
        self::assertFalse($this->list->isEmpty());
    }

    public function testIsEmptyEmpty(): void
    {
        self::assertTrue((new CypherList())->isEmpty());
    }

    public function testToArray(): void
    {
        self::assertEquals(['A', 'B', 'C'], $this->list->toArray());
    }

    public function testMerge(): void
    {
        self::assertEquals(new CypherList(['A', 'B', 'C', 'A', 'B', 'C']), $this->list->merge($this->list));
    }

    public function testHasKey(): void
    {
        self::assertFalse($this->list->hasKey(-1));
        self::assertTrue($this->list->hasKey(0));
        self::assertTrue($this->list->hasKey(1));
        self::assertTrue($this->list->hasKey(2));
        self::assertFalse($this->list->hasKey(3));
    }

    public function testFilterPermissive(): void
    {
        $filter = $this->list->filter(static fn () => true);

        self::assertEquals($this->list, $filter);
        self::assertNotSame($this->list, $filter);
    }

    public function testFilterBlock(): void
    {
        $filter = $this->list->filter(static fn () => false);

        self::assertEquals(new CypherList(), $filter);
    }

    public function testFilterSelective(): void
    {
        $filter = $this->list->filter(static fn (int $i, string $x) => $x === 'B' || $i === 2);

        self::assertEquals(new CypherList(['B', 'C']), $filter);
    }

    public function testMap(): void
    {
        $filter = $this->list->map(static fn (int $i, string $x) => $i.':'.$x);

        self::assertEquals(new CypherList(['0:A', '1:B', '2:C']), $filter);
    }

    public function testReduce(): void
    {
        $count = $this->list->reduce(static function (?int $initial, int $key, string $value) {
            return ($initial ?? 0) + $key * hexdec($value);
        }, 5);

        self::assertEquals(5 + hexdec('B') + 2 * hexdec('C'), $count);
    }

    public function testFind(): void
    {
        self::assertFalse($this->list->find('X'));
        self::assertEquals(0, $this->list->find('A'));
        self::assertEquals(1, $this->list->find('B'));
        self::assertEquals(2, $this->list->find('C'));
    }

    public function testReversed(): void
    {
        self::assertEquals(new CypherList(['C', 'B', 'A']), $this->list->reversed());
        self::assertEquals(new CypherList(['A', 'B', 'C']), $this->list);
        self::assertEquals(new CypherList(['A', 'B', 'C']), $this->list->reversed()->reversed());
    }

    public function testSliceSingle(): void
    {
        $sliced = $this->list->slice(1, 1);
        self::assertEquals(new CypherList(['B']), $sliced);
    }

    public function testSliceDouble(): void
    {
        $sliced = $this->list->slice(1, 2);
        self::assertEquals(new CypherList(['B', 'C']), $sliced);
    }

    public function testSliceAll(): void
    {
        $sliced = $this->list->slice(0, 3);
        self::assertEquals(new CypherList(['A', 'B', 'C']), $sliced);
    }

    public function testSliceTooMuch(): void
    {
        $sliced = $this->list->slice(0, 5);
        self::assertEquals(new CypherList(['A', 'B', 'C']), $sliced);
    }

    public function testSliceEmpty(): void
    {
        $sliced = $this->list->slice(0, 0);
        self::assertEquals(new CypherList(), $sliced);
    }

    public function testGetValid(): void
    {
        self::assertEquals('A', $this->list->get(0));
        self::assertEquals('B', $this->list->get(1));
        self::assertEquals('C', $this->list->get(2));
    }

    public function testFirst(): void
    {
        self::assertEquals('A', $this->list->first());
    }

    public function testFirstInvalid(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Cannot grab first element of an empty list');
        (new CypherList())->first();
    }

    public function testLast(): void
    {
        self::assertEquals('C', $this->list->last());
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
        $this->list->get(3);
    }

    public function testGetNegative(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Cannot get item in sequence at position: -1');
        $this->list->get(-1);
    }

    public function testIteration(): void
    {
        $counter = 0;
        foreach ($this->list as $key => $item) {
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

        $this->list[0] = 'a';
    }

    public function testOffsetUnset(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Laudis\Neo4j\Types\CypherList is immutable');

        unset($this->list[0]);
    }

    public function testOffsetGetValid(): void
    {
        self::assertEquals('A', $this->list[0]);
        self::assertEquals('B', $this->list[1]);
        self::assertEquals('C', $this->list[2]);
    }

    public function testOffsetGetInvalid(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Offset: "3" does not exists in object of instance: Laudis\Neo4j\Types\CypherList');
        $this->list[3];
    }

    public function testOffsetGetNegative(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Offset: "-1" does not exists in object of instance: Laudis\Neo4j\Types\CypherList');
        $this->list[-1];
    }

    public function testIssetValid(): void
    {
        self::assertTrue(isset($this->list[0]));
        self::assertTrue(isset($this->list[1]));
        self::assertTrue(isset($this->list[2]));
    }

    public function testIssetInValid(): void
    {
        self::assertFalse(isset($this->list[-1]));
        self::assertFalse(isset($this->list[3]));
    }

    public function testIssetValidNull(): void
    {
        self::assertTrue(isset((new CypherList([null]))[0]));
    }

    public function testJsonSerialize(): void
    {
        self::assertEquals('["A","B","C"]', json_encode($this->list, JSON_THROW_ON_ERROR));
    }

    public function testJsonSerializeEmpty(): void
    {
        self::assertEquals('[]', json_encode(new CypherList(), JSON_THROW_ON_ERROR));
    }

    public function testJoin(): void
    {
        self::assertEquals('A;B;C', $this->list->join(';'));
    }

    public function testJoinEmpty(): void
    {
        self::assertEquals('', (new CypherList())->join('A'));
    }

    public function testSortedDefault(): void
    {
        self::assertEquals($this->list, $this->list->sorted());
        self::assertEquals($this->list, $this->list->reversed()->sorted());
    }

    public function testSortedCustom(): void
    {
        $sorted = $this->list->sorted(static fn (string $x, string $y): int => -1 * ($x <=> $y));

        self::assertEquals(new CypherList(['C', 'B', 'A']), $sorted);
        self::assertEquals(new CypherList(['A', 'B', 'C']), $this->list);
    }
}
