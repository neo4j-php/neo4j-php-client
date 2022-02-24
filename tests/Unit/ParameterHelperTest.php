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

use Bolt\structures\DateTimeZoneId;
use DateTime;
use DateTimeZone;
use InvalidArgumentException;
use Iterator;
use Laudis\Neo4j\ParameterHelper;
use PHPUnit\Framework\TestCase;
use stdClass;

final class ParameterHelperTest extends TestCase
{
    /** @var iterable<iterable|scalar|null> */
    private static iterable $invalidIterable;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        /** @psalm-suppress MixedPropertyTypeCoercion */
        self::$invalidIterable = new class() implements Iterator {
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
        ])->toArray());
    }

    public function testFormatParameterInteger(): void
    {
        self::assertEquals([2 => 'b', 3 => 'd'], ParameterHelper::formatParameters([
            2 => 'b',
            3 => 'd',
        ])->toArray());
    }

    public function testFormatParameterVector(): void
    {
        self::assertEquals(['b', 'd'], ParameterHelper::formatParameters([
            'b',
            'd',
        ])->toArray());
    }

    public function testFormatParameterIterable(): void
    {
        self::assertEquals([[1, 2]], ParameterHelper::formatParameters([
            [1, 2],
        ])->toArray());
    }

    public function testFormatParameterInvalidIterable(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ParameterHelper::formatParameters(self::$invalidIterable);
    }

    public function testFormatParameterInvalidIterable2(): void
    {
        $this->expectException(InvalidArgumentException::class);
        ParameterHelper::formatParameters([
            'a' => [
                self::$invalidIterable,
            ],
        ]);
    }

    public function testAsParameterEmptyVector(): void
    {
        $result = ParameterHelper::asParameter([]);
        self::assertIsArray($result);
        self::assertCount(0, $result);
    }

    public function testAsParameterEmptyMap(): void
    {
        $result = ParameterHelper::asParameter([]);
        self::assertIsArray($result);
    }

    public function testAsParameterEmptyArray(): void
    {
        $result = ParameterHelper::asParameter([]);
        self::assertIsArray($result);
    }

    public function testStringable(): void
    {
        $result = ParameterHelper::asParameter(new class() {
            public function __toString(): string
            {
                return 'abc';
            }
        });
        self::assertEquals('abc', $result);
    }

    public function testInvalidType(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot format parameter of type: stdClass to work with Neo4J');
        ParameterHelper::asParameter(new stdClass());
    }

    public function testDateTime(): void
    {
        $date = ParameterHelper::asParameter(new DateTime('now', new DateTimeZone('Europe/Brussels')), true);

        self::assertInstanceOf(DateTimeZoneId::class, $date);
        self::assertEquals('Europe/Brussels', $date->tz_id());
    }
}
