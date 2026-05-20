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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use RuntimeException;
use Throwable;

abstract class EnvironmentAwareIntegrationTest extends TestCase
{
    protected Session $session;
    protected Driver $driver;
    protected Uri $uri;
    protected Neo4jLogger $logger;

    /**
     * The mock PSR-3 logger that drives existing mock-based assertions in tests
     * such as Neo4jLoggerTest. Behind the scenes it is one half of a {@see TeeLogger}
     * paired with {@see $capturedLogger}, so mock expectations keep working as before.
     */
    protected MockObject&LoggerInterface $mockLogger;

    /**
     * Captures every PSR-3 call the driver emits during the test. Stays silent when
     * tests pass; the contents are dumped to STDERR by {@see onNotSuccessfulTest()}
     * (or always if NEO4J_DEBUG_LOGS=1 is set in the environment), making it easy
     * to see exactly which Bolt messages were sent and what FAILURE was returned.
     */
    protected InMemoryTestLogger $capturedLogger;

    public function setUp(): void
    {
        parent::setUp();

        $connection = $_ENV['CONNECTION'] ?? false;
        if (!is_string($connection)) {
            $connection = 'bolt://localhost';
        }

        $this->mockLogger = $this->createMock(LoggerInterface::class);
        $this->capturedLogger = new InMemoryTestLogger();
        $teeLogger = new TeeLogger([$this->mockLogger, $this->capturedLogger]);

        /** @noinspection PhpUnhandledExceptionInspection */
        $conf = DriverConfiguration::default()->withLogger(LogLevel::DEBUG, $teeLogger);
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
     * Dump the captured wire log to STDERR whenever a test does not pass.
     * PHPUnit shows STDERR alongside the failure, so this gives every failing
     * integration test a full trace of ROUTE/BEGIN/RUN/FAILURE messages for free.
     */
    protected function onNotSuccessfulTest(Throwable $t): never
    {
        $this->dumpCapturedLog('FAILURE');

        throw $t;
    }

    protected function tearDown(): void
    {
        if (($_ENV['NEO4J_DEBUG_LOGS'] ?? '') === '1') {
            $this->dumpCapturedLog('END');
        }

        parent::tearDown();
    }

    private function dumpCapturedLog(string $marker): void
    {
        if (!isset($this->capturedLogger)) {
            return;
        }

        $header = sprintf("\n--- Neo4j wire log [%s] for %s::%s ---\n", $marker, static::class, $this->name());
        fwrite(STDERR, $header.$this->capturedLogger->dump()."\n--- end wire log ---\n");
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
