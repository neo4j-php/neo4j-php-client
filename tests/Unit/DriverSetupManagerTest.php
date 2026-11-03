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
use Laudis\Neo4j\ClientBuilder;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DriverSetupManagerTest extends TestCase
{
    public function testFailedGetDriverDoesNotDrainConfiguredUris(): void
    {
        $client = ClientBuilder::create()
            ->withDriver('default', 'bolt://127.0.0.1:17999', Authenticate::basic('neo4j', 'password'))
            ->withDefaultDriver('default')
            ->build();

        $first = null;
        $second = null;

        try {
            $client->verifyConnectivity();
        } catch (RuntimeException $e) {
            $first = $e->getMessage();
        }

        try {
            $client->verifyConnectivity();
        } catch (RuntimeException $e) {
            $second = $e->getMessage();
        }

        self::assertNotNull($first);
        self::assertNotNull($second);
        self::assertStringContainsString('bolt://127.0.0.1:17999', $first);
        self::assertStringContainsString('bolt://127.0.0.1:17999', $second);
    }
}
