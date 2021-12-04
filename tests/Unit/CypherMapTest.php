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
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use function json_encode;
use const JSON_THROW_ON_ERROR;
use Laudis\Neo4j\Databags\Pair;
use Laudis\Neo4j\Exception\RuntimeTypeException;
use Laudis\Neo4j\Types\ArrayList;
use Laudis\Neo4j\Types\CypherMap;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use stdClass;

final class CypherMapTest extends TestCase
{
    /** @var CypherMap<string> */
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
        $filter = $this->map->filter(static fn (string $x, string $i) => !($i === 'B' || $x === 'z'));

        self::assertEquals(new CypherMap(['A' => 'x']), $filter);
    }

    public function testMap(): void
    {
        $filter = $this->map->map(static fn (string $x, string $i) => $i.':'.$x);

        self::assertEquals(new CypherMap(['A' => 'A:x', 'B' => 'B:y', 'C' => 'C:z']), $filter);
    }

    public function testReduce(): void
    {
        $count = $this->map->reduce(static function (?int $initial, string $key, string $value) {
            return ($initial ?? 0) + ord($value) + ord($key);
        }, 5);

        self::assertEquals(5 + ord('A') + ord('x') + ord('B') + ord('y') + ord('C') + ord('z'), $count);
    }

    public function testFind(): void
    {
        self::assertFalse($this->map->find('A'));
        self::assertFalse($this->map->find('X'));
        self::assertEquals('C', $this->map->find('z'));
        self::assertEquals('B', $this->map->find('y'));
        self::assertEquals('A', $this->map->find('x'));
    }

    public function testReversed(): void
    {
        self::assertEquals(new CypherMap(['C' => 'z', 'B' => 'y', 'A' => 'x']), $this->map->reversed());
        self::assertEquals(new CypherMap(['A' => 'x', 'B' => 'y', 'C' => 'z']), $this->map);
        self::assertEquals(new CypherMap(['A' => 'x', 'B' => 'y', 'C' => 'z']), $this->map->reversed()->reversed());
    }

    public function testSliceSingle(): void
    {
        $sliced = $this->map->slice(1, 1);
        self::assertEquals(new CypherMap(['B' => 'y']), $sliced);
    }

    public function testSliceDouble(): void
    {
        $sliced = $this->map->slice(1, 2);
        self::assertEquals(new CypherMap(['B' => 'y', 'C' => 'z']), $sliced);
    }

    public function testSliceAll(): void
    {
        $sliced = $this->map->slice(0, 3);
        self::assertEquals($this->map, $sliced);
    }

    public function testSliceTooMuch(): void
    {
        $sliced = $this->map->slice(0, 5);
        self::assertEquals($this->map, $sliced);
    }

    public function testSliceEmpty(): void
    {
        $sliced = $this->map->slice(0, 0);
        self::assertEquals(new CypherMap(), $sliced);
    }

    public function testGetValid(): void
    {
        self::assertEquals('x', $this->map->get('A'));
        self::assertEquals('y', $this->map->get('B'));
        self::assertEquals('z', $this->map->get('C'));
    }

    public function testGetDefault(): void
    {
        self::assertEquals('x', $this->map->get('A', null));
        self::assertNull($this->map->get('x', null));
        self::assertEquals(new stdClass(), $this->map->get('Cd', new stdClass()));
    }

    public function testFirst(): void
    {
        self::assertEquals(new Pair('A', 'x'), $this->map->first());
    }

    public function testFirstInvalid(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Cannot grab first element of an empty map');
        (new CypherMap())->first();
    }

    public function testLast(): void
    {
        self::assertEquals(new Pair('C', 'z'), $this->map->last());
    }

    public function testLastInvalid(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Cannot grab last element of an empty map');
        (new CypherMap())->last();
    }

    public function testGetInvalid(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Cannot get item in sequence with key: a');
        $this->map->get('a');
    }

    public function testIteration(): void
    {
        $counter = 0;
        foreach ($this->map as $key => $item) {
            ++$counter;
            self::assertEquals(['A' => 'x', 'B' => 'y', 'C' => 'z'][$key], $item);
        }
        self::assertEquals(3, $counter);
    }

    public function testIterationEmpty(): void
    {
        $counter = 0;
        foreach ((new CypherMap()) as $key => $item) {
            ++$counter;
            self::assertEquals(['A' => 'x'][$key], $item);
        }
        self::assertEquals(0, $counter);
    }

    public function testOffsetSet(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Laudis\Neo4j\Types\CypherMap is immutable');

        $this->map['A'] = 'a';
    }

    public function testOffsetUnset(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Laudis\Neo4j\Types\CypherMap is immutable');

        unset($this->map['A']);
    }

    public function testOffsetGetValid(): void
    {
        self::assertEquals('x', $this->map['A']);
        self::assertEquals('y', $this->map['B']);
        self::assertEquals('z', $this->map['C']);
    }

    public function testOffsetGetInvalid(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Offset: "AA" does not exists in object of instance: Laudis\Neo4j\Types\CypherMap');
        $this->map['AA'];
    }

    public function testIssetValid(): void
    {
        self::assertTrue(isset($this->map['A']));
        self::assertTrue(isset($this->map['B']));
        self::assertTrue(isset($this->map['C']));
    }

    public function testIssetInValid(): void
    {
        self::assertFalse(isset($this->map['a']));
    }

    public function testIssetValidNull(): void
    {
        self::assertTrue(isset((new CypherMap(['a' => null]))['a']));
    }

    public function testJsonSerialize(): void
    {
        self::assertEquals('{"A":"x","B":"y","C":"z"}', json_encode($this->map, JSON_THROW_ON_ERROR));
    }

    public function testJsonSerializeEmpty(): void
    {
        self::assertEquals('{}', json_encode(new CypherMap(), JSON_THROW_ON_ERROR));
    }

    public function testJoin(): void
    {
        self::assertEquals('x;y;z', $this->map->join(';'));
    }

    public function testJoinEmpty(): void
    {
        self::assertEquals('', (new CypherMap())->join('A'));
    }

    public function testDiff(): void
    {
        $subtract = new CypherMap(['B' => 'x', 'Z' => 'z']);
        $result = $this->map->diff($subtract);

        self::assertEquals(new CypherMap(['A' => 'x', 'C' => 'z']), $result);
        self::assertEquals(new CypherMap(['B' => 'x', 'Z' => 'z']), $subtract);
        self::assertEquals(new CypherMap(['A' => 'x', 'B' => 'y', 'C' => 'z']), $this->map);
    }

    public function testDiffEmpty(): void
    {
        self::assertEquals(new CypherMap(['A' => 'x', 'B' => 'y', 'C' => 'z']), $this->map->diff([]));
    }

    public function testIntersect(): void
    {
        $intersect = new CypherMap(['B' => 'x', 'Z' => 'z']);
        $result = $this->map->intersect($intersect);

        self::assertEquals(new CypherMap(['B' => 'y']), $result);
        self::assertEquals(new CypherMap(['B' => 'x', 'Z' => 'z']), $intersect);
        self::assertEquals(new CypherMap(['A' => 'x', 'B' => 'y', 'C' => 'z']), $this->map);
    }

    public function testUnion(): void
    {
        $intersect = new CypherMap(['B' => 'x', 'Z' => 'z']);
        $result = $this->map->union($intersect);

        self::assertEquals(new CypherMap(['A' => 'x', 'B' => 'y', 'C' => 'z', 'Z' => 'z']), $result);
        self::assertEquals(new CypherMap(['B' => 'x', 'Z' => 'z']), $intersect);
        self::assertEquals(new CypherMap(['A' => 'x', 'B' => 'y', 'C' => 'z']), $this->map);
    }

    public function testXor(): void
    {
        $intersect = new CypherMap(['B' => 'x', 'Z' => 'z']);
        $result = $this->map->xor($intersect);

        self::assertEquals(new CypherMap(['A' => 'x', 'C' => 'z', 'Z' => 'z']), $result);
        self::assertEquals(new CypherMap(['B' => 'x', 'Z' => 'z']), $intersect);
        self::assertEquals(new CypherMap(['A' => 'x', 'B' => 'y', 'C' => 'z']), $this->map);
    }

    public function testValue(): void
    {
        self::assertEquals(new ArrayList(['x', 'y', 'z']), $this->map->values());
    }

    public function testKeys(): void
    {
        self::assertEquals(new ArrayList(['A', 'B', 'C']), $this->map->keys());
    }

    public function testPairs(): void
    {
        $list = new ArrayList([new Pair('A', 'x'), new Pair('B', 'y'), new Pair('C', 'z')]);
        self::assertEquals($list, $this->map->pairs());
    }

    public function testSkip(): void
    {
        self::assertEquals(new Pair('A', 'x'), $this->map->skip(0));
        self::assertEquals(new Pair('B', 'y'), $this->map->skip(1));
        self::assertEquals(new Pair('C', 'z'), $this->map->skip(2));
    }

    public function testSkipInvalid(): void
    {
        $this->expectException(OutOfBoundsException::class);
        $this->expectExceptionMessage('Cannot skip to a pair at position: 4');
        self::assertEquals(new Pair('A', 'x'), $this->map->skip(4));
    }

    public function testInvalidConstruct(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Iterable must have a stringable keys');

        new CypherMap(new class() implements IteratorAggregate {
            public function getIterator(): Generator
            {
                yield new stdClass() => 'x';
            }
        });
    }

    public function testSortedDefault(): void
    {
        self::assertEquals($this->map, $this->map->sorted());
        self::assertEquals($this->map, $this->map->reversed()->sorted());
    }

    public function testSortedCustom(): void
    {
        $sorted = $this->map->sorted(static fn (string $x, string $y): int => -1 * ($x <=> $y));

        self::assertEquals(new CypherMap(['C' => 'z', 'B' => 'y', 'A' => 'x']), $sorted);
        self::assertEquals(new CypherMap(['A' => 'x', 'B' => 'y', 'C' => 'z']), $this->map);
    }

    public function testKSorted(): void
    {
        self::assertEquals($this->map, $this->map->ksorted());
        self::assertEquals($this->map, $this->map->reversed()->ksorted());
    }

    public function testKSortedCustom(): void
    {
        $sorted = $this->map->ksorted(static fn (string $x, string $y) => -1 * ($x <=> $y));

        self::assertEquals(new CypherMap(['C' => 'z', 'B' => 'y', 'A' => 'x']), $sorted);
        self::assertEquals(new CypherMap(['A' => 'x', 'B' => 'y', 'C' => 'z']), $this->map);
    }

    public function testCasts(): void
    {
        $map = new CypherMap(['a' => null]);

        self::assertEquals('', $map->getAsString('a'));

        $this->expectException(RuntimeTypeException::class);
        $map->getAsCartesian3DPoint('a');
    }

    public function getMap(): void
    {
        $map = CypherMap::fromIterable(['a' => 'b', 'c' => 'd'])
            ->map(static function (string $value, string $key) {
                $tbr = new stdClass();

                $tbr->key = $key;
                $tbr->value = $value;

                return $tbr;
            })
            ->map(static function (stdClass $class) {
                return (string) $class->value;
            });

        self::assertEquals(CypherMap::fromIterable(['a' => 'b', 'c' => 'd']), $map);
    }
}
