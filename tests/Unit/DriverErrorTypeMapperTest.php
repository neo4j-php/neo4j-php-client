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

use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Exception\Neo4jException;
use Laudis\Neo4j\TestkitBackend\DriverErrorTypeMapper;
use PHPUnit\Framework\TestCase;

final class DriverErrorTypeMapperTest extends TestCase
{
    public function testMapsTransientServerErrors(): void
    {
        $exception = new Neo4jException([
            Neo4jError::fromMessageAndCode(
                'Neo.TransientError.Database.DatabaseUnavailable',
                'database unavailable',
            ),
        ]);

        self::assertSame(
            'Laudis\\Neo4j\\Exception\\TransientException',
            DriverErrorTypeMapper::map($exception),
        );
        self::assertTrue(DriverErrorTypeMapper::isRetryable($exception));
    }

    public function testMapsConnectionErrors(): void
    {
        $exception = new Neo4jException([
            Neo4jError::fromMessageAndCode(
                'Neo.ClientError.General.ConnectionError',
                'connection reset',
            ),
        ]);

        self::assertSame(
            'Laudis\\Neo4j\\Exception\\ServiceUnavailableException',
            DriverErrorTypeMapper::map($exception),
        );
        self::assertFalse(DriverErrorTypeMapper::isRetryable($exception));
    }
}
