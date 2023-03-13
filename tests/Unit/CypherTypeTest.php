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

use BadMethodCallException;

use function json_encode;

use JsonException;
use Laudis\Neo4j\Types\AbstractCypherObject;
use OutOfBoundsException;
use PHPUnit\Framework\TestCase;

/**
 * @extends AbstractCypherObject<string, mixed>
 *
 * @psalm-immutable
 */
final class BogusCypherObject extends AbstractCypherObject
{
    public function toArray(): array
    {
        return [];
    }
}

/**
 * @extends AbstractCypherObject<string, mixed>
 *
 * @psalm-immutable
 */
final class BogusCypherObjectFilled extends AbstractCypherObject
{
    public function toArray(): array
    {
        return [
            'a' => 'b',
            'c' => 'd',
        ];
    }
}

final class CypherTypeTest extends TestCase
{
    /**
     * @throws JsonException
     *
     * @psalm-suppress all
     */
    public function testEmpty(): void
    {
        $empty = new BogusCypherObject();

        self::assertEquals('[]', json_encode($empty, JSON_THROW_ON_ERROR));
        self::assertCount(0, $empty);
        self::assertNull($empty[0] ?? null);

        $caught = null;
        try {
            $empty[0] = 'abc';
        } catch (BadMethodCallException) {
            $caught = true;
        }
        self::assertTrue($caught, 'Empty is writable');

        $caught = null;
        try {
            unset($empty[0]);
        } catch (BadMethodCallException) {
            $caught = true;
        }
        self::assertTrue($caught, 'Empty is writable');

        $caught = null;
        try {
            $empty[0];
        } catch (OutOfBoundsException) {
            $caught = true;
        }
        self::assertTrue($caught, 'Empty has still valid access');
    }

    /**
     * @throws JsonException
     *
     * @psalm-suppress all
     */
    public function testFilled(): void
    {
        $filled = new BogusCypherObjectFilled();

        self::assertEquals('{"a":"b","c":"d"}', json_encode($filled, JSON_THROW_ON_ERROR));

        self::assertFalse($filled->offsetExists(0));
        self::assertNull($filled[0] ?? null);

        self::assertTrue($filled->offsetExists('a'));
        self::assertTrue($filled->offsetExists('c'));
        self::assertEquals('b', $filled['a']);
        self::assertEquals('d', $filled['c']);

        $caught = null;
        try {
            $filled[0] = 'abc';
        } catch (BadMethodCallException) {
            $caught = true;
        }
        self::assertTrue($caught, 'Filled is writable');
        $caught = null;
        try {
            unset($filled[0]);
        } catch (BadMethodCallException) {
            $caught = true;
        }
        self::assertTrue($caught, 'Filled is writable');

        $caught = null;
        try {
            $filled[0];
        } catch (OutOfBoundsException) {
            $caught = true;
        }
        self::assertTrue($caught, 'Filled has still valid access');
    }
}
