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

use Dotenv\Dotenv;

use function is_string;

use Laudis\Neo4j\Basic\Driver;
use Laudis\Neo4j\Basic\Session;
use Laudis\Neo4j\Common\Uri;
use PHPUnit\Framework\TestCase;

abstract class EnvironmentAwareIntegrationTest extends TestCase
{
    protected static Session $session;
    protected static Driver $driver;
    protected static Uri $uri;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        Dotenv::createImmutable(__DIR__.'/../../')->safeLoad();
        $connection = $_ENV['CONNECTION'] ?? false;
        if (!is_string($connection)) {
            $connection = 'bolt://localhost';
        }

        self::$uri = Uri::create($connection);
        self::$driver = Driver::create(self::$uri);
        self::$session = self::$driver->createSession();
    }

    public function getSession(array|string|null $forceScheme = null): Session
    {
        $this->skipUnsupportedScheme($forceScheme);

        return self::$session;
    }

    public function getUri(array|string|null $forceScheme = null): Uri
    {
        $this->skipUnsupportedScheme($forceScheme);

        return self::$uri;
    }

    private function skipUnsupportedScheme(array|string|null $forceScheme): void
    {
        if (is_string($forceScheme)) {
            $forceScheme = [$forceScheme];
        }

        if ($forceScheme !== null &&
            !in_array(self::$uri->getScheme(), $forceScheme)
        ) {
            /** @psalm-suppress MixedArgumentTypeCoercion */
            $this->markTestSkipped(sprintf(
                'Connection only for types: "%s"',
                implode(', ', $forceScheme)
            ));
        }
    }

    protected function getDriver(array|string|null $forceScheme = null): Driver
    {
        $this->skipUnsupportedScheme($forceScheme);

        return self::$driver;
    }
}
