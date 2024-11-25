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

namespace Laudis\Neo4j\Common;

use function array_key_exists;
use function array_key_first;
use function array_reduce;

use Bolt\error\ConnectException;
use Countable;
use InvalidArgumentException;
use Laudis\Neo4j\Authentication\Authenticate;
use Laudis\Neo4j\Contracts\DriverInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Databags\DriverConfiguration;
use Laudis\Neo4j\Databags\DriverSetup;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\DriverFactory;

use const PHP_INT_MIN;

use Psr\Log\LogLevel;
use RuntimeException;
use SplPriorityQueue;

use function sprintf;

/**
 * @template ResultFormat
 */
class DriverSetupManager implements Countable
{
    private const DEFAULT_DRIVER_CONFIG = 'bolt://localhost:7687';

    /** @var array<string, SplPriorityQueue<int, DriverSetup>> */
    private array $driverSetups = [];
    /** @var array<string, DriverInterface<ResultFormat>> */
    private array $drivers = [];
    private ?string $default = null;

    /**
     * @psalm-mutation-free
     *
     * @param FormatterInterface<ResultFormat> $formatter
     */
    public function __construct(
        private FormatterInterface $formatter,
        private DriverConfiguration $configuration
    ) {}

    public function getDriverConfiguration(): DriverConfiguration
    {
        return $this->configuration;
    }

    /**
     * @psalm-mutation-free
     */
    public function withDriverConfiguration(DriverConfiguration $config): self
    {
        $tbr = clone $this;

        $tbr->configuration = $config;

        return $tbr;
    }

    /**
     * @psalm-mutation-free
     */
    public function withSetup(DriverSetup $setup, ?string $alias = null, ?int $priority = 0): self
    {
        $alias ??= $this->decideAlias($alias);

        $setups = $this->driverSetups;

        /** @var SplPriorityQueue<int, DriverSetup> */
        $setups[$alias] ??= new SplPriorityQueue();
        /** @psalm-suppress ImpureMethodCall */
        $setups[$alias]->insert($setup, $priority ?? 0);

        $tbr = clone $this;
        $tbr->driverSetups = $setups;

        return $tbr;
    }

    public function hasDriver(string $alias): bool
    {
        return array_key_exists($alias, $this->driverSetups);
    }

    /**
     * @return DriverInterface<ResultFormat>
     */
    public function getDriver(SessionConfiguration $config, ?string $alias = null): DriverInterface
    {
        $alias ??= $this->decideAlias($alias);

        if (!array_key_exists($alias, $this->driverSetups)) {
            if ($alias !== 'default') {
                throw new InvalidArgumentException(sprintf('Cannot find a driver setup with alias: "%s"', $alias));
            }

            /** @var SplPriorityQueue<int, DriverSetup> */
            $this->driverSetups['default'] = new SplPriorityQueue();
            $setup = new DriverSetup(Uri::create(self::DEFAULT_DRIVER_CONFIG), Authenticate::disabled($config->getLogger()));
            $this->driverSetups['default']->insert($setup, PHP_INT_MIN);

            return $this->getDriver($config);
        }

        if (array_key_exists($alias, $this->drivers)) {
            return $this->drivers[$alias];
        }

        $urisTried = [];
        foreach ($this->driverSetups[$alias] as $setup) {
            $uri = $setup->getUri();
            $auth = $setup->getAuth();

            $driver = DriverFactory::create($uri, $this->configuration, $auth, $this->formatter);
            $urisTried[] = $uri->__toString();
            if ($driver->verifyConnectivity($config)) {
                $this->drivers[$alias] = $driver;

                return $driver;
            }
        }

        throw new RuntimeException(sprintf('Cannot connect to any server on alias: %s with Uris: (\'%s\')', $alias, implode('\', ', array_unique($urisTried))));
    }

    public function verifyConnectivity(SessionConfiguration $config, ?string $alias = null): bool
    {
        try {
            $this->getDriver($config, $alias);
        } catch (ConnectException $e) {
            $this->getLogger()?->log(
                LogLevel::WARNING,
                sprintf('Could not connect to server using alias (%s)', $alias ?? '<default>'),
                ['exception' => $e]
            );

            return false;
        }

        return true;
    }

    /**
     * @psalm-mutation-free
     */
    private function decideAlias(?string $alias): string
    {
        return $alias ?? $this->default ?? array_key_first($this->driverSetups) ?? 'default';
    }

    public function getDefaultAlias(): string
    {
        return $this->decideAlias(null);
    }

    /**
     * @psalm-mutation-free
     */
    public function withDefault(string $default): self
    {
        $tbr = clone $this;
        $tbr->default = $default;

        return $tbr;
    }

    public function count(): int
    {
        return array_reduce($this->driverSetups, static fn (int $acc, SplPriorityQueue $x) => $acc + $x->count(), 0);
    }

    /**
     * @template U
     *
     * @psalm-mutation-free
     */
    public function withFormatter(FormatterInterface $formatter): self
    {
        $tbr = clone $this;
        $tbr->formatter = $formatter;

        return $tbr;
    }

    /**
     * @psalm-mutation-free
     */
    public function getLogger(): ?Neo4jLogger
    {
        return $this->configuration->getLogger();
    }
}
