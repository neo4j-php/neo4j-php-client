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
        /** @var MockObject $logger */
        $logger = $this->getNeo4jLogger()->getLogger();
        /** @var Session $session */
        $session = $this->getSession();

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
                    'sessionConfig' => new SessionConfiguration(null, null, null, null, null),
                ],
            ],
        ];
        $logger->expects(self::exactly(3))->method('info')->willReturnCallback(
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
                'GOODBYE',
                [],
            ],
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
                    'mode' => 'w',
                ],
            ],
            [
                'DISCARD',
                [],
            ],
        ];
        $logger->expects(self::exactly(7))->method('debug')->willReturnCallback(
            static function (string $message, array $context) use (&$debugLogs) {
                $debugLogs[] = [$message, $context];
            }
        );

        $session->run('RETURN 1 as test');

        self::assertEquals($expectedInfoLogs, $infoLogs);
        self::assertEquals($expectedDebugLogs, $debugLogs);
    }
}
