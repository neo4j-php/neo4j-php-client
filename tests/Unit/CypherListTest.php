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

use function array_sum;
use ArrayIterator;
use BadMethodCallException;
use Generator;
use function hexdec;
use function json_encode;
use Laudis\Neo4j\Databags\Pair;
use Laudis\Neo4j\Types\CypherList;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use function range;
use stdClass;

/**
 * @psalm-suppress MixedOperand
 * @psalm-suppress MixedAssignment
 */
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
        self::assertEquals($this->list->toArray(), $fromIterable->toArray());
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
        self::assertEquals($this->list->toArray(), $fromIterable->toArray());
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
        self::assertEquals($this->list->toArray(), $copy->toArray());
    }

    public function testCopyDepth(): void
    {
        $list = new CypherList([new stdClass()]);
        $copy = $list->copy();

        self::assertNotSame($list, $copy);
        self::assertEquals($list->toArray(), $copy->toArray());
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
        self::assertEquals((new CypherList(['A', 'B', 'C', 'A', 'B', 'C']))->toArray(), $this->list->merge($this->list)->toArray());
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
        $filter = $this->list->filter(static fn () => true)->toArray();

        self::assertEquals($this->list->toArray(), $filter);
        self::assertNotSame($this->list, $filter);
    }

    public function testFilterBlock(): void
    {
        $filter = $this->list->filter(static fn () => false)->toArray();

        self::assertEquals([], $filter);
    }

    public function testFilterSelective(): void
    {
        $filter = $this->list->filter(static fn (string $x, int $i) => $x === 'B' || $i === 2)->toArray();

        self::assertEquals(['B', 'C'], $filter);
    }

    public function testMap(): void
    {
        $filter = $this->list->map(static fn (string $x, int $i) => $i.':'.$x)->toArray();

        self::assertEquals(['0:A', '1:B', '2:C'], $filter);
    }

    public function testReduce(): void
    {
        $count = $this->list->reduce(static function (?int $initial, string $value, int $key) {
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
        self::assertEquals(['C', 'B', 'A'], $this->list->reversed()->toArray());
        self::assertEquals(['A', 'B', 'C'], $this->list->toArray());
        self::assertEquals(['A', 'B', 'C'], $this->list->reversed()->reversed()->toArray());
    }

    public function testSliceSingle(): void
    {
        $sliced = $this->list->slice(1, 1);
        self::assertEquals(['B'], $sliced->toArray());
    }

    public function testSliceDouble(): void
    {
        $sliced = $this->list->slice(1, 2);
        self::assertEquals(['B', 'C'], $sliced->toArray());
    }

    public function testSliceAll(): void
    {
        $sliced = $this->list->slice(0, 3);
        self::assertEquals(['A', 'B', 'C'], $sliced->toArray());
    }

    public function testSliceTooMuch(): void
    {
        $sliced = $this->list->slice(0, 5);
        self::assertEquals(['A', 'B', 'C'], $sliced->toArray());
    }

    public function testSliceEmpty(): void
    {
        $sliced = $this->list->slice(0, 0);
        self::assertEquals([], $sliced->toArray());
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
        $this->expectExceptionMessage('Offset: "3" does not exists in object of instance: Laudis\Neo4j\Types\CypherList');
        $this->list->get(3);
    }

    public function testGetNegative(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Offset: "-1" does not exists in object of instance: Laudis\Neo4j\Types\CypherList');
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
        self::assertEquals($this->list->toArray(), $this->list->sorted()->toArray());
        self::assertEquals($this->list->toArray(), $this->list->reversed()->sorted()->toArray());
    }

    public function testSortedCustom(): void
    {
        $sorted = $this->list->sorted(static fn (string $x, string $y): int => -1 * ($x <=> $y));

        self::assertEquals(['C', 'B', 'A'], $sorted->toArray());
        self::assertEquals(['A', 'B', 'C'], $this->list->toArray());
    }

    public function testEach(): void
    {
        $cntr = -1;
        /** @psalm-suppress UnusedClosureParam */
        $this->list->each(static function (string $x, int $key) use (&$cntr) { $cntr = $key; });

        self::assertEquals($this->list->count() - 1, $cntr);
    }

    public function testMapTypings(): void
    {
        $map = CypherList::fromIterable(['a', 'b', 'c'])
            ->map(static function (string $value, int $key): stdClass {
                $tbr = new stdClass();

                $tbr->key = $key;
                $tbr->value = $value;

                return $tbr;
            })
            ->map(static function (stdClass $class) {
                return (string) $class->value;
            })
            ->toArray();

        self::assertEquals(['a', 'b', 'c'], $map);
    }

    public function testKeyBy(): void
    {
        $object = new stdClass();
        $object->x = 'stdClassX';
        $object->y = 'wrong';
        $list = CypherList::fromIterable([
            1,
            $object,
            ['x' => 'arrayX', 'y' => 'wrong'],
            'wrong',
        ])->pluck('x');

        self::assertEquals(['stdClassX', 'arrayX'], $list->toArray());
    }

    public function testCombined(): void
    {
        $i = 0;
        $list = CypherList::fromIterable([0, 1, 2, 3])->map(static function ($x) use (&$i) {
            ++$i;

            return $x;
        });

        /** @var int $i */
        self::assertEquals(0, $i);

        $pairs = $list->map(static fn ($x, $index): Pair => new Pair($index, $x));
        self::assertEquals(0, $i);

        self::assertCount(4, $pairs);
        self::assertEquals(4, $i);

        self::assertCount(4, $list);
        self::assertEquals(4, $i);
    }

    public function testSlice(): void
    {
        $sumBefore = 0;
        $sumAfter = 0;
        $range = CypherList::fromIterable($this->infiniteIterator())
            ->map(static function ($x) use (&$sumBefore) {
                $sumBefore += $x;

                return $x;
            })
            ->slice(5, 3)
            ->map(static function ($x) use (&$sumAfter) {
                $sumAfter += $x;

                return $x;
            });

        /** @var int $sumBefore */
        /** @var int $sumAfter */
        $start = $range->get(0);

        self::assertEquals(5, $start);

        self::assertEquals(array_sum(range(0, 5)), $sumBefore);
        self::assertEquals(5, $sumAfter);

        $end = $range->get(2);

        self::assertEquals(7, $end);
        self::assertEquals(array_sum(range(0, 7)), $sumBefore);
        self::assertEquals(array_sum(range(5, 7)), $sumAfter);
    }

    /**
     * @return Generator<int, int>
     */
    private function infiniteIterator(): Generator
    {
        $i = 0;
        while (true) {
            yield $i;
            ++$i;
        }
    }
}
