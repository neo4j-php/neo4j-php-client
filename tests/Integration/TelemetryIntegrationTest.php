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

use Laudis\Neo4j\Bolt\BoltConnection;
use Laudis\Neo4j\Bolt\BoltDriver;
use Laudis\Neo4j\Bolt\ConnectionPool;
use Laudis\Neo4j\Common\GeneratorHelper;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Tests\EnvironmentAwareIntegrationTest;
use ReflectionProperty;

/**
 * E2E checks against Neo4j Aura (telemetry.enabled hint + driver APIs).
 */
final class TelemetryIntegrationTest extends EnvironmentAwareIntegrationTest
{
    private function requireAuraConnection(): void
    {
        $connection = $_ENV['CONNECTION'] ?? '';
        if (!is_string($connection) || !str_contains($connection, 'databases.neo4j.io')) {
            $this->markTestSkipped('Set CONNECTION to a Neo4j Aura URI (e.g. neo4j+s://….databases.neo4j.io) for Aura telemetry E2E.');
        }
    }

    public function testAuraAdvertisesTelemetryEnabledHint(): void
    {
        $this->requireAuraConnection();

        $driver = BoltDriver::create($this->uri, DriverConfiguration::default());
        $connection = $this->acquireBoltConnection($driver);

        try {
            if (!self::readServerTelemetryEnabled($connection)) {
                $this->markTestSkipped('Server did not advertise telemetry.enabled (expected on Aura when Bolt 5.4 telemetry is enabled).');
            }
        } finally {
            $this->releaseBoltConnection($driver, $connection);
        }
    }

    public function testSessionApisWorkWithTelemetryOnAura(): void
    {
        $this->requireAuraConnection();

        $session = $this->getSession(['neo4j', 'bolt']);

        self::assertSame(1, $session->run('RETURN 1 AS n')->first()->get('n'));

        $value = $session->readTransaction(static fn ($tsx) => $tsx->run('RETURN 2 AS n')->first()->get('n'));
        self::assertSame(2, $value);

        $tx = $session->beginTransaction();
        self::assertSame(3, $tx->run('RETURN 3 AS n')->first()->get('n'));
        $tx->commit();
    }

    public function testTelemetryDisabledStillWorksOnAura(): void
    {
        $this->requireAuraConnection();

        $driver = BoltDriver::create(
            $this->uri,
            DriverConfiguration::default()->withTelemetryDisabled(true),
        );
        $session = $driver->createSession();
        self::assertSame(1, $session->run('RETURN 1 AS n')->first()->get('n'));
    }

    private function acquireBoltConnection(BoltDriver $driver): BoltConnection
    {
        $pool = $this->getPool($driver);

        /** @var BoltConnection */
        return GeneratorHelper::getReturnFromGenerator($pool->acquire(SessionConfiguration::default()));
    }

    private function releaseBoltConnection(BoltDriver $driver, BoltConnection $connection): void
    {
        $this->getPool($driver)->release($connection);
    }

    private function getPool(BoltDriver $driver): ConnectionPool
    {
        $property = new ReflectionProperty(BoltDriver::class, 'pool');

        /** @var ConnectionPool */
        return $property->getValue($driver);
    }

    private static function readServerTelemetryEnabled(BoltConnection $connection): bool
    {
        $property = new ReflectionProperty(BoltConnection::class, 'serverTelemetryEnabled');

        return $property->getValue($connection) === true;
    }
}
