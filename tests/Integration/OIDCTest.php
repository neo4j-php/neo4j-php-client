<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Tests\Integration;

use function array_key_exists;
use function is_string;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Http\HttpDriver;
use Monolog\Test\TestCase;

/**
 * @psalm-suppress MissingConstructor
 */
final class OIDCTest extends TestCase
{
    public function testConnect(): void
    {
        $this->expectNotToPerformAssertions();
        if (!array_key_exists('ACCESS_TOKEN_BEARER', $_ENV) || !is_string($_ENV['ACCESS_TOKEN_BEARER'])) {
            $this->markTestSkipped('No OIDC token provided');
        }

        /** @var mixed */
        $connections = $_ENV['NEO4J_CONNECTIONS'] ?? '';
        $connections = is_string($connections) ? $connections : '';
        foreach (explode(',', $connections) as $connection) {
            $driver = ClientBuilder::create()
                ->withDriver('default', $connection, Authenticate::oidc($_ENV['ACCESS_TOKEN_BEARER']))
                ->build()
                ->getDriver('default');

            if ($driver instanceof HttpDriver) {
                continue;
            }

            $driver->createSession()->run('RETURN 1');
        }
    }
}
