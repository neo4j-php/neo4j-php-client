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

namespace Laudis\Neo4j\Tests\Integration;

use Laudis\Neo4j\Bolt\Session;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\Statement;
use Laudis\Neo4j\Databags\TransactionConfiguration;
use Laudis\Neo4j\Tests\EnvironmentAwareIntegrationTest;
use PHPUnit\Framework\MockObject\MockObject;

class Neo4jLoggerTest extends EnvironmentAwareIntegrationTest
{
    public function testLogger(): void
    {
        if ($this->getUri()->getScheme() === 'http') {
            self::markTestSkipped('This test is not applicable for the HTTP driver');
        }

        // Close connections so that we can test the logger logging
        // during authentication while acquiring a new connection
        $this->driver->closeConnections();

        /** @var MockObject $logger */
        $logger = $this->getNeo4jLogger()->getLogger();
        /** @var Session $session */
        $session = $this->getSession();

        /** @var array<int, array> $infoLogs */
        $infoLogs = [];
        $expectedInfoLogs = [
            [
                'Running statements',
                [
                    'statements' => [new Statement('RETURN 1 as test', [])],
                ],
            ],
            [
                'Starting instant transaction',
                [
                    'config' => new TransactionConfiguration(null, null),
                ],
            ],
            [
                'Acquiring connection',
                [
                    'config' => new TransactionConfiguration(null, null),
                ],
            ],
        ];
        $logger->expects(self::exactly(count($expectedInfoLogs)))->method('info')->willReturnCallback(
            static function (string $message, array $context) use (&$infoLogs) {
                $infoLogs[] = [$message, $context];
            }
        );

        $debugLogs = [];
        $expectedDebugLogs = [
            [
                'HELLO',
                [
                    'user_agent' => 'neo4j-php-client/2',
                ],
            ],
            [
                'LOGON',
                [
                    'scheme' => 'basic',
                    'principal' => 'neo4j',
                ],
            ],
            [
                'RUN',
                [
                    'text' => 'RETURN 1 as test',
                    'parameters' => [],
                    'extra' => [
                        'mode' => 'w',
                    ],
                ],
            ],
            [
                'DISCARD',
                [],
            ],
        ];

        if ($this->getUri()->getScheme() === 'neo4j') {
            array_splice(
                $expectedDebugLogs,
                0,
                0,
                [
                    [
                        'HELLO',
                        [
                            'user_agent' => 'neo4j-php-client/2',
                        ],
                    ],
                    [
                        'LOGON',
                        [
                            'scheme' => 'basic',
                            'principal' => 'neo4j',
                        ],
                    ],
                    [
                        'ROUTE',
                        [
                            'db' => null,
                        ],
                    ],
                    [
                        'GOODBYE',
                        [],
                    ],
                ],
            );
        }

        $logger->expects(self::exactly(count($expectedDebugLogs)))->method('debug')->willReturnCallback(
            static function (string $message, array $context) use (&$debugLogs) {
                $debugLogs[] = [$message, $context];
            }
        );

        $session->run('RETURN 1 as test');

        self::assertCount(3, $infoLogs);
        self::assertEquals(array_slice($expectedInfoLogs, 0, 2), array_slice($infoLogs, 0, 2));
        /**
         * @psalm-suppress PossiblyUndefinedIntArrayOffset
         */
        self::assertEquals($expectedInfoLogs[2][0], $infoLogs[2][0]);
        /**
         * @psalm-suppress PossiblyUndefinedIntArrayOffset
         * @psalm-suppress MixedArrayAccess
         */
        self::assertInstanceOf(SessionConfiguration::class, $infoLogs[2][1]['sessionConfig']);

        self::assertEquals($expectedDebugLogs, $debugLogs);
    }
}
