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

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Common\DriverSetupManager;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\DriverSetup;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Formatter\SummarizedResultFormatter;
use PHPUnit\Framework\TestCase;
use ReflectionObject;
use RuntimeException;
use SplPriorityQueue;

final class DriverSetupManagerQueueTest extends TestCase
{
    public function testFailedGetDriverDoesNotDrainSetupQueue(): void
    {
        $uri = 'bolt://127.0.0.1:17999';
        $manager = (new DriverSetupManager(
            SummarizedResultFormatter::create(),
            DriverConfiguration::default()->withAcquireConnectionTimeout(0.5),
        ))->withSetup(
            new DriverSetup(Uri::create($uri), Authenticate::disabled(null)),
            'default',
            0,
        )->withDefault('default');

        $sessionConfig = SessionConfiguration::default();

        try {
            $manager->getDriver($sessionConfig, 'default');
            self::fail('Expected RuntimeException for unreachable Bolt host');
        } catch (RuntimeException $e) {
            self::assertStringContainsString($uri, $e->getMessage());
        }

        $reflection = new ReflectionObject($manager);
        $property = $reflection->getProperty('driverSetups');
        $property->setAccessible(true);
        /** @var array<string, SplPriorityQueue<int, DriverSetup>> $setups */
        $setups = $property->getValue($manager);

        self::assertArrayHasKey('default', $setups);
        self::assertSame(1, $setups['default']->count(), 'Setup queue must survive a failed getDriver() pass');

        try {
            $manager->getDriver($sessionConfig, 'default');
            self::fail('Expected RuntimeException on second getDriver()');
        } catch (RuntimeException $e) {
            self::assertStringContainsString($uri, $e->getMessage());
            self::assertStringNotContainsString("Uris: ('')", $e->getMessage());
        }
    }
}
