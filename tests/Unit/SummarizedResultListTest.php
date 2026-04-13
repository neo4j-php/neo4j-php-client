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

use Laudis\Neo4j\Databags\SummarizedResult;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use Laudis\Neo4j\Types\CypherList;
use Laudis\Neo4j\Types\CypherMap;
use PHPUnit\Framework\TestCase;

/**
 * Mirrors TestKit stub: next() then list() must return only remaining rows (no rewind / duplicate).
 *
 * @psalm-import-type OGMTypes from SummarizedResultFormatter
 */
final class SummarizedResultListTest extends TestCase
{
    public function testListAfterNextOmitsConsumedRow(): void
    {
        $summary = null;
        $inner = (new CypherList(function (): iterable {
            for ($i = 1; $i <= 5; ++$i) {
                yield new CypherMap(['n' => $i]);
            }
        }))->withCacheLimit(2);

        /** @var CypherList<CypherMap<OGMTypes>> $recordsList */
        $recordsList = (new CypherList($inner))->withCacheLimit(2);
        $result = new SummarizedResult($summary, $recordsList, ['n'], null, null);

        self::assertTrue($result->valid());
        $first = $result->current()->get('n');
        self::assertIsInt($first);
        self::assertSame(1, $first);
        $result->next();

        $rows = $result->list();
        self::assertCount(4, $rows);

        $nValues = [];
        foreach ($rows as $row) {
            self::assertInstanceOf(CypherMap::class, $row);
            $n = $row->get('n');
            self::assertIsInt($n);
            $nValues[] = $n;
        }
        self::assertSame([2, 3, 4, 5], $nValues);
    }
}
