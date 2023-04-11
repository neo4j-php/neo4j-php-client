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

use function array_key_exists;

use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Basic\Driver;

/**
 * @psalm-suppress MissingConstructor
 */
final class OIDCTest extends EnvironmentAwareIntegrationTest
{
    public function testConnect(): void
    {
        $this->expectNotToPerformAssertions();
        if (!array_key_exists('ACCESS_TOKEN_BEARER', $_ENV)) {
            $this->markTestSkipped('No OIDC token provided');
        }

        $driver = Driver::create($this->getUri(['bolt', 'neo4j']), authenticate: Authenticate::oidc($_ENV['ACCESS_TOKEN_BEARER']));

        $driver->createSession()->run('RETURN 1');
    }
}
