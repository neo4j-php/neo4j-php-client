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

use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use PHPUnit\Framework\TestCase;

final class CypherListPeekTest extends TestCase
{
    public function testPeekReturnsNullOnEmptyResult(): void
    {
        $list = new CypherList([]);

        self::assertFalse($list->valid());
        self::assertNull($list->peek());
    }

    public function testPeekMatchesCurrentAndDoesNotAdvance(): void
    {
        $list = new CypherList([
            new CypherMap(['n' => 1]),
            new CypherMap(['n' => 2]),
        ]);

        $a = $list->peek();
        $b = $list->peek();
        $c = $list->current();

        self::assertNotNull($a);
        self::assertSame($a, $b);
        self::assertSame($a, $c);
        self::assertSame(1, $a->get('n'));

        $list->next();

        self::assertSame(2, $list->peek()?->get('n'));
    }

    public function testPeekReturnsNullAfterAllRowsConsumed(): void
    {
        $list = new CypherList([new CypherMap(['x' => true])]);
        foreach ($list as $_) {
        }
        self::assertFalse($list->valid());
        self::assertNull($list->peek());
    }
}
