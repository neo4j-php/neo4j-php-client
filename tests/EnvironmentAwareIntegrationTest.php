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
use RuntimeException;

abstract class EnvironmentAwareIntegrationTest extends TestCase
{
    protected Session $session;
    protected Driver $driver;
    protected Uri $uri;
    protected Neo4jLogger $logger;

    public function setUp(): void
    {
        parent::setUp();

        $connection = $_ENV['CONNECTION'] ?? false;
        if (!is_string($connection)) {
            $connection = 'bolt://localhost';
        }

        /** @noinspection PhpUnhandledExceptionInspection */
        $conf = DriverConfiguration::default()->withLogger(LogLevel::DEBUG, $this->createMock(LoggerInterface::class));
        $logger = $conf->getLogger();
        if ($logger === null) {
            throw new RuntimeException('Logger not set');
        }
        $this->logger = $logger;
        $this->uri = Uri::create($connection);
        $this->driver = Driver::create($this->uri, $conf);
        $this->session = $this->driver->createSession();
    }

    /**
     * @param list<string>|string|null $forceScheme
     */
    public function getSession(array|string|null $forceScheme = null): Session
    {
        $this->skipUnsupportedScheme($forceScheme);

        return $this->session;
    }

    /**
     * @param list<string>|string|null $forceScheme
     */
    public function getUri(array|string|null $forceScheme = null): Uri
    {
        $this->skipUnsupportedScheme($forceScheme);

        return $this->uri;
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

        if (!in_array($this->uri->getScheme(), $options)) {
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

        return $this->driver;
    }

    protected function getNeo4jLogger(): Neo4jLogger
    {
        return $this->logger;
    }
}
