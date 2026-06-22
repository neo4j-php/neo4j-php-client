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
use Laudis\Neo4j\Bolt\PullPartialFailureException;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use PHPUnit\Framework\TestCase;

final class BoltResultTest extends TestCase
{
    public function testYieldsBufferedRowsBeforeDeferredPullFailure(): void
    {
        $failure = new Neo4jException([
            Neo4jError::fromMessageAndCode(
                'Neo.TransientError.Database.DatabaseUnavailable',
                'database unavailable',
            ),
        ]);

        $connection = $this->createMock(BoltConnection::class);
        $connection->expects(self::once())
            ->method('pull')
            ->willThrowException(new PullPartialFailureException([[5]], $failure));

        $result = new BoltResult($connection, 2, -1);
        $iterator = $result->getIt();

        self::assertTrue($iterator->valid());
        self::assertSame([5], $iterator->current());

        $this->expectExceptionObject($failure);
        $iterator->next();
    }
}
