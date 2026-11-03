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

use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\BoltResult;
use Laudis\Neo4j\Bolt\PullResult;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use PHPUnit\Framework\TestCase;

final class BoltResultTest extends TestCase
{
    public function testBufferKeepsRemainingRowsReadable(): void
    {
        $connection = $this->createMock(BoltConnection::class);
        $connection->expects($this->exactly(3))->method('pull')->willReturnOnConsecutiveCalls(
            PullResult::complete([[1], [2], ['has_more' => true]]),
            PullResult::complete([[3], ['has_more' => true]]),
            PullResult::complete([[4], ['bookmark' => 'bm']]),
        );

        $result = new BoltResult($connection, 2, -1);
        $summaries = [];
        $result->addFinishedCallback(static function (array $meta) use (&$summaries): void {
            $summaries[] = $meta;
        });

        $rows = [$result->current()];
        $result->buffer();

        $this->assertSame([['bookmark' => 'bm']], $summaries);

        $result->next();
        while ($result->valid()) {
            $rows[] = $result->current();
            $result->next();
        }

        $this->assertSame([[1], [2], [3], [4]], $rows);
        $this->assertCount(1, $summaries);
    }

    public function testBufferStopsAtDeferredFailure(): void
    {
        $failure = new Neo4jException([Neo4jError::fromMessageAndCode('Neo.ClientError.Statement.ArithmeticError', '/ by zero')]);

        $connection = $this->createMock(BoltConnection::class);
        $connection->expects($this->exactly(2))->method('pull')->willReturnOnConsecutiveCalls(
            PullResult::complete([[1], ['has_more' => true]]),
            PullResult::withDeferredFailure([[2]], $failure),
        );

        $result = new BoltResult($connection, 1, -1);
        $result->buffer();

        $rows = [];
        try {
            foreach ($result as $row) {
                $rows[] = $row;
            }
            $this->fail('Expected the deferred failure once the buffered rows were read');
        } catch (Neo4jException $e) {
            $this->assertSame($failure, $e);
        }

        $this->assertSame([[1], [2]], $rows);
    }
}
