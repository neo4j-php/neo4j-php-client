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

use Bolt\enum\Message;
use Bolt\enum\Signature;
use Bolt\protocol\Response;
use InvalidArgumentException;
use Laudis\Neo4j\Databags\Neo4jError;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class Bolt57ResponseParserTest extends TestCase
{
    public function testFailureReadsLegacyCode(): void
    {
        $error = Neo4jError::fromBoltResponse(new Response(Message::LOGON, Signature::FAILURE, [
            'code' => 'Neo.ClientError.Security.Unauthorized',
            'message' => 'wrong password',
        ]));

        self::assertSame('Neo.ClientError.Security.Unauthorized', $error->getCode());
        self::assertSame('wrong password', $error->getMessage());
    }

    public function testFailureReadsNeo4jCodeFromBolt57(): void
    {
        $error = Neo4jError::fromBoltResponse(new Response(Message::LOGON, Signature::FAILURE, [
            'neo4j_code' => 'Neo.ClientError.Security.Unauthorized',
            'message' => 'wrong password',
            'gql_status' => '50N42',
        ]));

        self::assertSame('Neo.ClientError.Security.Unauthorized', $error->getCode());
    }

    public function testFailureWithoutACodeIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Neo4jError::fromBoltResponse(new Response(Message::LOGON, Signature::FAILURE, [
            'message' => 'wrong password',
        ]));
    }

    public function testStatusesKeepOnlyNotifications(): void
    {
        $method = new ReflectionMethod(SummarizedResultFormatter::class, 'boltNotifications');
        $method->setAccessible(true);
        $notifications = $method->invoke(SummarizedResultFormatter::create(), [
            'statuses' => [
                [
                    'gql_status' => '00000',
                    'status_description' => 'note: successful completion',
                ],
                [
                    'gql_status' => '03N90',
                    'status_description' => 'warn: cartesian product',
                    'description' => 'This query builds a cartesian product between disconnected patterns.',
                    'neo4j_code' => 'Neo.ClientNotification.Statement.CartesianProduct',
                    'title' => 'Cartesian product',
                    'diagnostic_record' => [
                        '_severity' => 'WARNING',
                        '_classification' => 'PERFORMANCE',
                        '_position' => ['offset' => 0, 'line' => 1, 'column' => 1],
                    ],
                ],
            ],
        ]);

        self::assertSame([
            [
                'severity' => 'WARNING',
                'description' => 'This query builds a cartesian product between disconnected patterns.',
                'code' => 'Neo.ClientNotification.Statement.CartesianProduct',
                'position' => ['offset' => 0, 'line' => 1, 'column' => 1],
                'title' => 'Cartesian product',
                'category' => 'PERFORMANCE',
            ],
        ], $notifications);
    }

    public function testStatusesFallBackToStatusDescriptionWhenLegacyDescriptionIsAbsent(): void
    {
        $method = new ReflectionMethod(SummarizedResultFormatter::class, 'boltNotifications');
        $method->setAccessible(true);
        $notifications = $method->invoke(SummarizedResultFormatter::create(), [
            'statuses' => [
                [
                    'gql_status' => '03N90',
                    'status_description' => 'warn: cartesian product',
                    'neo4j_code' => 'Neo.ClientNotification.Statement.CartesianProduct',
                    'diagnostic_record' => [
                        '_severity' => 'WARNING',
                        '_classification' => 'PERFORMANCE',
                    ],
                ],
            ],
        ]);

        self::assertSame('warn: cartesian product', $notifications[0]['description']);
    }
}
