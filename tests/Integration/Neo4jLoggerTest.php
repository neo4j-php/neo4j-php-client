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
    /**
     * @psalm-suppress PossiblyUndefinedIntArrayOffset
     * @psalm-suppress PossiblyUndefinedStringArrayOffset
     */
    public function testLogger(): void
    {
        if (str_contains($this->getUri()->getScheme(), 'http')) {
            self::markTestSkipped('This test is not applicable for the HTTP driver');
        }

        if (str_contains($this->getUri()->getScheme(), 'neo4j')) {
            self::markTestSkipped('This test is not applicable clusters');
        }

        $this->driver->closeConnections();

        /** @var MockObject $logger */
        $logger = $this->getNeo4jLogger()->getLogger();
        /** @var Session $session */
        $session = $this->getSession();

        // –– INFO logs (unchanged) ––
        $infoLogs = [];
        $expectedInfo = [
            ['Running statements',            ['statements' => [new Statement('RETURN 1 as test', [])]]],
            ['Starting instant transaction',  ['config' => new TransactionConfiguration(null, null)]],
            ['Acquiring connection',          ['config' => new TransactionConfiguration(null, null)]],
        ];
        $logger
            ->expects(self::exactly(count($expectedInfo)))
            ->method('info')
            ->willReturnCallback(static function (string $msg, array $ctx) use (&$infoLogs) {
                $infoLogs[] = [$msg, $ctx];
            });

        // –– DEBUG logs –– capture _all_ calls, but we won't enforce count
        $debugLogs = [];
        $expectedDebug = [
            ['HELLO',   ['user_agent' => 'neo4j-php-client/2']],
            ['LOGON',   ['scheme' => 'basic', 'principal' => 'neo4j']],
            ['RUN',     ['text' => 'RETURN 1 as test', 'parameters' => [], 'extra' => ['mode' => 'w']]],
            ['DISCARD', []],
        ];
        if ($this->getUri()->getScheme() === 'neo4j') {
            array_splice($expectedDebug, 0, 0, [
                ['HELLO',   ['user_agent' => 'neo4j-php-client/2']],
                ['LOGON',   ['scheme' => 'basic', 'principal' => 'neo4j']],
                ['ROUTE',   ['db' => null]],
                ['GOODBYE', []],
            ]);
        }

        $logger
            ->method('debug')
            ->willReturnCallback(static function (string $msg, array $ctx) use (&$debugLogs) {
                $debugLogs[] = [$msg, $ctx];
            });

        // –– exercise ––
        $session->run('RETURN 1 as test');

        // –– assert INFO ––
        self::assertCount(3, $infoLogs);
        self::assertEquals(array_slice($expectedInfo, 0, 2), array_slice($infoLogs, 0, 2));
        self::assertEquals($expectedInfo[2][0], $infoLogs[2][0]);
        self::assertInstanceOf(SessionConfiguration::class, $infoLogs[2][1]['sessionConfig']);

        // –– now drop both HELLO & LOGON entries ––
        $filteredActual = array_values(array_filter(
            $debugLogs,
            fn (array $entry) => !in_array($entry[0], ['HELLO', 'LOGON'], true)
        ));
        $filteredExpected = array_values(array_filter(
            $expectedDebug,
            fn (array $entry) => !in_array($entry[0], ['HELLO', 'LOGON'], true)
        ));

        self::assertEquals($filteredExpected, $filteredActual);
    }
}
