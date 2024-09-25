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

namespace Laudis\Neo4j\Tests;

use function is_string;

use Laudis\Neo4j\Basic\Driver;
use Laudis\Neo4j\Basic\Session;
use Laudis\Neo4j\Common\Neo4jLogger;
use Laudis\Neo4j\Common\Uri;
use Laudis\Neo4j\Databags\DriverConfiguration;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

abstract class EnvironmentAwareIntegrationTest extends TestCase
{
    private static bool $isSetUp = false;
    protected static Session $session;
    protected static Driver $driver;
    protected static Uri $uri;
    protected static Neo4jLogger $logger;

    public function setUp(): void
    {
        parent::setUp();

        $connection = $_ENV['CONNECTION'] ?? false;
        if (!is_string($connection)) {
            $connection = 'bolt://localhost';
        }

        if (self::$isSetUp) {
            return;
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        $conf = DriverConfiguration::default()->withLogger(LogLevel::DEBUG, $this->createMock(LoggerInterface::class));
        self::$logger = $conf->getLogger();
        self::$uri = Uri::create($connection);
        self::$driver = Driver::create(self::$uri, $conf);
        self::$session = self::$driver->createSession();
        self::$isSetUp = true;
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

    protected function getNeo4jLogger(): Neo4jLogger
    {
        return self::$logger;
    }
}
