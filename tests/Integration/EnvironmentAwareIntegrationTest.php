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

        $connection = $_ENV['CONNECTION'] ?? false;
        if (!is_string($connection)) {
            $connection = 'bolt://localhost';
        }

        self::$uri = Uri::create($connection);
        self::$driver = Driver::create(self::$uri);
        self::$session = self::$driver->createSession();
    }

    /**
     * @param list<string>|string|null $forceScheme
     */
    public function getSession(array|string|null $forceScheme = null): Session
    {
        $this->skipUnsupportedScheme($forceScheme);

        return self::$session;
    }

    /**
     * @param list<string>|string|null $forceScheme
     */
    public function getUri(array|string|null $forceScheme = null): Uri
    {
        $this->skipUnsupportedScheme($forceScheme);

        return self::$uri;
    }

    /**
     * @param list<string>|string|null $forceScheme
     */
    private function skipUnsupportedScheme(array|string|null $forceScheme): void
    {
        if ($forceScheme === null) {
            return;
        }

        if (is_string($forceScheme)) {
            $forceScheme = [$forceScheme];
        }

        $options = [];
        foreach ($forceScheme as $scheme) {
            $options[] = $scheme;
            $options[] = $scheme.'+s';
            $options[] = $scheme.'+ssc';
        }

        if (!in_array(self::$uri->getScheme(), $options)) {
            /** @psalm-suppress MixedArgumentTypeCoercion */
            $this->markTestSkipped(sprintf(
                'Connection only for types: "%s"',
                implode(', ', $forceScheme)
            ));
        }
    }

    /**
     * @param list<string>|string|null $forceScheme
     */
    protected function getDriver(array|string|null $forceScheme = null): Driver
    {
        $this->skipUnsupportedScheme($forceScheme);

        return self::$driver;
    }
}
