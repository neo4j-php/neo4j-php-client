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
use InvalidArgumentException;
use Laudis\Neo4j\Exception\InvalidTypeCast;
use Laudis\Neo4j\TypeCaster;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use PHPUnit\Framework\TestCase;
use stdClass;
use Stringable;

/**
 * @psalm-type ExpectationRow = array<string, mixed>
 */
final class TypeCasterTest extends TestCase
{
    /**
     * Complete coverage: every input type × every cast method.
     * When a cast isn't possible, expected is null (invalid case).
     *
     * Yields argument lists for {@see testCastMatrix} in order: input, method, expected, class (null unless toClass).
     *
     * @return Generator<string, array{0: mixed, 1: string, 2: mixed, 3: string|null}>
     */
    public static function provideCastMatrix(): Generator
    {
        $stringable = new class implements Stringable {
            public function __toString(): string
            {
                return 'stringable-value';
            }
        };
        $stringableNumeric = new class implements Stringable {
            public function __toString(): string
            {
                return '42';
            }
        };
        $stringableNumericFloat = new class implements Stringable {
            public function __toString(): string
            {
                return '42.5';
            }
        };
        $stdObj = new stdClass();

        $inputs = [
            'null' => null,
            'int' => 42,
            'int_zero' => 0,
            'int_one' => 1,
            'float' => 3.14,
            'float_zero' => 0.0,
            'bool_true' => true,
            'bool_false' => false,
            'string_empty' => '',
            'string_nonempty' => 'hello',
            'string_numeric_int' => '123',
            'string_numeric_float' => '99.9',
            'string_one' => '1',
            'string_zero' => '0',
            'string_non_numeric' => 'abc',
            'string_true' => 'true',
            'string_forty_two' => '42',
            'stringable' => $stringable,
            'stringable_numeric' => $stringableNumeric,
            'stringable_numeric_float' => $stringableNumericFloat,
            'array_indexed' => [1, 2, 3],
            'array_associative' => ['a' => 1, 'b' => 2],
            'array_empty' => [],
            'ArrayIterator' => new ArrayIterator([10, 20]),
            'Generator' => null, // Created fresh per row (consumable)
            'CypherList' => new CypherList([1, 2, 3]),
            'CypherMap' => new CypherMap(['x' => 1, 'y' => 2]),
            'stdClass' => $stdObj,
        ];

        $matrix = self::getExpectedMatrix($stringable, $stringableNumeric, $stringableNumericFloat, $stdObj);

        foreach ($inputs as $inputName => $inputValue) {
            foreach ($matrix as $method => $expectations) {
                $expected = self::expectedValueForInput($expectations, $inputName);
                $key = $inputName.'->'.$method;

                // Generator must be fresh per test (consumable once)
                $actualInput = $inputName === 'Generator'
                    ? (static function (): Generator {
                        yield 1;
                        yield 2;
                    })()
                    : $inputValue;

                $classForRow = null;
                if ($method === 'toClass') {
                    $classForRow = self::toClassClassNameForInput($expectations, $inputName);
                }

                yield $key => [$actualInput, $method, $expected, $classForRow];
            }
        }
    }

    /**
     * Values come from {@see getExpectedMatrix()} literals; array access stays `mixed` to Psalm.
     *
     * @param ExpectationRow $row
     *
     * @return bool|int|float|string|object|array<mixed>|array<string, mixed>|null
     *
     * @psalm-suppress MixedReturnStatement
     */
    private static function expectedValueForInput(array $row, string $inputName)
    {
        return $row[$inputName] ?? null;
    }

    /**
     * @param ExpectationRow $expectations
     */
    private static function toClassClassNameForInput(array $expectations, string $inputName): string
    {
        $maybe = $expectations['_class'] ?? [];
        if (!is_array($maybe)) {
            return stdClass::class;
        }

        /** @var array<string, string> $classMap */
        $classMap = $maybe;

        return $classMap[$inputName] ?? stdClass::class;
    }

    /**
     * @return array<string, ExpectationRow>
     */
    private static function getExpectedMatrix(
        object $stringable,
        object $stringableNumeric,
        object $stringableNumericFloat,
        stdClass $stdObj,
    ): array {
        return [
            'toString' => [
                'null' => '',
                'int' => '42',
                'int_zero' => '0',
                'int_one' => '1',
                'float' => '3.14',
                'float_zero' => '0',
                'bool_true' => '1',
                'bool_false' => '',
                'string_empty' => '',
                'string_nonempty' => 'hello',
                'string_numeric_int' => '123',
                'string_numeric_float' => '99.9',
                'string_one' => '1',
                'string_zero' => '0',
                'string_non_numeric' => 'abc',
                'string_true' => 'true',
                'string_forty_two' => '42',
                'stringable' => 'stringable-value',
                'stringable_numeric' => '42',
                'stringable_numeric_float' => '42.5',
                'array_indexed' => null,
                'array_associative' => null,
                'array_empty' => null,
                'ArrayIterator' => null,
                'Generator' => null,
                'CypherList' => null,
                'CypherMap' => null,
                'stdClass' => null,
            ],
            'toInt' => [
                'null' => null,
                'int' => 42,
                'int_zero' => 0,
                'int_one' => 1,
                'float' => 3,
                'float_zero' => 0,
                'bool_true' => 1,
                'bool_false' => 0,
                'string_empty' => null,
                'string_nonempty' => null,
                'string_numeric_int' => 123,
                'string_numeric_float' => 99,
                'string_one' => 1,
                'string_zero' => 0,
                'string_non_numeric' => null,
                'string_true' => null,
                'string_forty_two' => 42,
                'stringable' => null,
                'stringable_numeric' => 42,
                'stringable_numeric_float' => 42,
                'array_indexed' => null,
                'array_associative' => null,
                'array_empty' => null,
                'ArrayIterator' => null,
                'Generator' => null,
                'CypherList' => null,
                'CypherMap' => null,
                'stdClass' => null,
            ],
            'toFloat' => [
                'null' => null,
                'int' => 42.0,
                'int_zero' => 0.0,
                'int_one' => 1.0,
                'float' => 3.14,
                'float_zero' => 0.0,
                'bool_true' => 1.0,
                'bool_false' => 0.0,
                'string_empty' => null,
                'string_nonempty' => null,
                'string_numeric_int' => 123.0,
                'string_numeric_float' => 99.9,
                'string_one' => 1.0,
                'string_zero' => 0.0,
                'string_non_numeric' => null,
                'string_true' => null,
                'string_forty_two' => 42.0,
                'stringable' => null,
                'stringable_numeric' => 42.0,
                'stringable_numeric_float' => 42.5,
                'array_indexed' => null,
                'array_associative' => null,
                'array_empty' => null,
                'ArrayIterator' => null,
                'Generator' => null,
                'CypherList' => null,
                'CypherMap' => null,
                'stdClass' => null,
            ],
            'toBool' => [
                'null' => null,
                'int' => true,
                'int_zero' => false,
                'int_one' => true,
                'float' => true,
                'float_zero' => false,
                'bool_true' => true,
                'bool_false' => false,
                'string_empty' => null,
                'string_nonempty' => null,
                'string_numeric_int' => true,
                'string_numeric_float' => true,
                'string_one' => true,
                'string_zero' => false,
                'string_non_numeric' => null,
                'string_true' => null,
                'string_forty_two' => true,
                'stringable' => null,
                'stringable_numeric' => true,
                'stringable_numeric_float' => true,
                'array_indexed' => null,
                'array_associative' => null,
                'array_empty' => null,
                'ArrayIterator' => null,
                'Generator' => null,
                'CypherList' => null,
                'CypherMap' => null,
                'stdClass' => null,
            ],
            'toClass' => [
                '_class' => [
                    'null' => stdClass::class,
                    'int' => stdClass::class,
                    'int_zero' => stdClass::class,
                    'int_one' => stdClass::class,
                    'float' => stdClass::class,
                    'float_zero' => stdClass::class,
                    'bool_true' => stdClass::class,
                    'bool_false' => stdClass::class,
                    'string_empty' => stdClass::class,
                    'string_nonempty' => stdClass::class,
                    'string_numeric_int' => stdClass::class,
                    'string_numeric_float' => stdClass::class,
                    'string_one' => stdClass::class,
                    'string_zero' => stdClass::class,
                    'string_non_numeric' => stdClass::class,
                    'string_true' => stdClass::class,
                    'string_forty_two' => stdClass::class,
                    'stringable' => Stringable::class,
                    'stringable_numeric' => Stringable::class,
                    'stringable_numeric_float' => Stringable::class,
                    'array_indexed' => stdClass::class,
                    'array_associative' => stdClass::class,
                    'array_empty' => stdClass::class,
                    'ArrayIterator' => stdClass::class,
                    'Generator' => stdClass::class,
                    'CypherList' => stdClass::class,
                    'CypherMap' => stdClass::class,
                    'stdClass' => stdClass::class,
                ],
                'null' => null,
                'int' => null,
                'int_zero' => null,
                'int_one' => null,
                'float' => null,
                'float_zero' => null,
                'bool_true' => null,
                'bool_false' => null,
                'string_empty' => null,
                'string_nonempty' => null,
                'string_numeric_int' => null,
                'string_numeric_float' => null,
                'string_one' => null,
                'string_zero' => null,
                'string_non_numeric' => null,
                'string_true' => null,
                'string_forty_two' => null,
                'stringable' => $stringable,
                'stringable_numeric' => $stringableNumeric,
                'stringable_numeric_float' => $stringableNumericFloat,
                'array_indexed' => null,
                'array_associative' => null,
                'array_empty' => null,
                'ArrayIterator' => null,
                'Generator' => null,
                'CypherList' => null,
                'CypherMap' => null,
                'stdClass' => $stdObj,
            ],
            'toArray' => [
                'null' => null,
                'int' => null,
                'int_zero' => null,
                'int_one' => null,
                'float' => null,
                'float_zero' => null,
                'bool_true' => null,
                'bool_false' => null,
                'string_empty' => null,
                'string_nonempty' => null,
                'string_numeric_int' => null,
                'string_numeric_float' => null,
                'string_one' => null,
                'string_zero' => null,
                'string_non_numeric' => null,
                'string_true' => null,
                'string_forty_two' => null,
                'stringable' => null,
                'stringable_numeric' => null,
                'stringable_numeric_float' => null,
                'array_indexed' => [1, 2, 3],
                'array_associative' => [1, 2],
                'array_empty' => [],
                'ArrayIterator' => [10, 20],
                'Generator' => [1, 2],
                'CypherList' => [1, 2, 3],
                'CypherMap' => [1, 2],
                'stdClass' => null,
            ],
            'toCypherList' => [
                'null' => null,
                'int' => null,
                'int_zero' => null,
                'int_one' => null,
                'float' => null,
                'float_zero' => null,
                'bool_true' => null,
                'bool_false' => null,
                'string_empty' => null,
                'string_nonempty' => null,
                'string_numeric_int' => null,
                'string_numeric_float' => null,
                'string_one' => null,
                'string_zero' => null,
                'string_non_numeric' => null,
                'string_true' => null,
                'string_forty_two' => null,
                'stringable' => null,
                'stringable_numeric' => null,
                'stringable_numeric_float' => null,
                'array_indexed' => [1, 2, 3],
                'array_associative' => [1, 2],
                'array_empty' => [],
                'ArrayIterator' => [10, 20],
                'Generator' => [1, 2],
                'CypherList' => [1, 2, 3],
                'CypherMap' => [1, 2],
                'stdClass' => null,
            ],
            'toCypherMap' => [
                'null' => null,
                'int' => null,
                'int_zero' => null,
                'int_one' => null,
                'float' => null,
                'float_zero' => null,
                'bool_true' => null,
                'bool_false' => null,
                'string_empty' => null,
                'string_nonempty' => null,
                'string_numeric_int' => null,
                'string_numeric_float' => null,
                'string_one' => null,
                'string_zero' => null,
                'string_non_numeric' => null,
                'string_true' => null,
                'string_forty_two' => null,
                'stringable' => null,
                'stringable_numeric' => null,
                'stringable_numeric_float' => null,
                'array_indexed' => ['0' => 1, '1' => 2, '2' => 3],
                'array_associative' => ['a' => 1, 'b' => 2],
                'array_empty' => [],
                'ArrayIterator' => ['0' => 10, '1' => 20],
                'Generator' => ['0' => 1, '1' => 2],
                'CypherList' => ['0' => 1, '1' => 2, '2' => 3],
                'CypherMap' => ['x' => 1, 'y' => 2],
                'stdClass' => null,
            ],
        ];
    }

    /**
     * @dataProvider provideCastMatrix
     */
    public function testCastMatrix(mixed $input, string $method, mixed $expected, ?string $class = null): void
    {
        if ($expected === null) {
            $this->expectException(InvalidTypeCast::class);
        }

        /** @var class-string $classString */
        $classString = $class ?? stdClass::class;
        $result = match ($method) {
            'toString' => TypeCaster::toString($input),
            'toInt' => TypeCaster::toInt($input),
            'toFloat' => TypeCaster::toFloat($input),
            'toBool' => TypeCaster::toBool($input),
            'toClass' => TypeCaster::toClass($input, $classString),
            'toArray' => TypeCaster::toArray($input),
            'toCypherList' => TypeCaster::toCypherList($input),
            'toCypherMap' => TypeCaster::toCypherMap($input),
            default => throw new InvalidArgumentException("Unknown method: {$method}"),
        };

        if ($expected === null) {
            return;
        }

        if ($method === 'toCypherList') {
            self::assertInstanceOf(CypherList::class, $result);
            self::assertEquals($expected, $result->toArray());

            return;
        }

        if ($method === 'toCypherMap') {
            self::assertInstanceOf(CypherMap::class, $result);
            self::assertEquals($expected, $result->toArray());

            return;
        }

        if ($method === 'toClass') {
            self::assertSame($expected, $result);

            return;
        }

        if (is_int($expected) || is_bool($expected)) {
            self::assertSame($expected, $result);
        } else {
            self::assertEquals($expected, $result);
        }
    }

    public function testToNull(): void
    {
        self::assertNull(TypeCaster::toNull());
    }

    /**
     * toClass with wrong class throws InvalidTypeCast.
     */
    public function testToClassWithWrongClassThrowsInvalidTypeCast(): void
    {
        $stdObj = new stdClass();
        $this->expectException(InvalidTypeCast::class);
        $this->expectExceptionMessage('Cannot cast stdClass to Stringable');
        TypeCaster::toClass($stdObj, Stringable::class);
    }
}
