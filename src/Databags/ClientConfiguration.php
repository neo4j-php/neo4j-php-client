<?php

declare(strict_types=1);

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Databags;

use Ds\Map;
use Ds\Vector;
use function is_callable;
use Laudis\Neo4j\Contracts\DriverConfigurationInterface;
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Enum\AccessMode;

/**
 * @template T
 */
final class ClientConfiguration
{
    /** @var DriverConfigurationInterface<T> */
    private DriverConfigurationInterface $driverConfiguration;
    /** @var callable():(string|null)|string|null */
    private $defaultDriver;
    /** @var callable():(HttpPsrBindings|null)|HttpPsrBindings|null */
    private $psrBindings;

    /**
     * @param DriverConfigurationInterface<T>                        $driverConfiguration
     * @param callable():(string|null)|string|null                   $defaultDriver
     * @param callable():(HttpPsrBindings|null)|HttpPsrBindings|null $psrBindings
     */
    public function __construct($driverConfiguration, $defaultDriver, $psrBindings)
    {
        $this->driverConfiguration = $driverConfiguration;
        $this->defaultDriver = $defaultDriver;
        $this->psrBindings = $psrBindings;
    }

    /**
     * @template U
     *
     * @param DriverConfigurationInterface<U>                        $driverConfiguration
     * @param callable():(string|null)|string|null                   $defaultDriver
     * @param callable():(HttpPsrBindings|null)|HttpPsrBindings|null $psrBindings
     *
     * @return self<U>
     */
    public static function create($driverConfiguration, $defaultDriver = null, $psrBindings = null): self
    {
        return new self($driverConfiguration, $defaultDriver, $psrBindings);
    }

    public function getDriverConfiguration(): DriverConfigurationInterface
    {
        return $this->driverConfiguration;
    }

    /**
     * @return self<Vector<Map<string, scalar|array|null>>>
     */
    public static function default(): self
    {
        return self::create(DriverConfiguration::default());
    }

    public function getDefaultDriver(): ?string
    {
        $driver = $this->defaultDriver;

        return is_callable($driver) ? $driver() : $driver;
    }

    public function getAccessMode(): AccessMode
    {
        return $this->driverConfiguration->getSessionConfiguration()->getAccessMode();
    }

    public function getFetchSize(): int
    {
        return $this->driverConfiguration->getSessionConfiguration()->getFetchSize();
    }

    public function getTransactionTimeout(): float
    {
        return $this->driverConfiguration->getTransactionConfiguration()->getTimeout();
    }

    public function getHttpPsrBindings(): HttpPsrBindings
    {
        $psrBindings = $this->psrBindings;
        $psrBindings = is_callable($psrBindings) ? $psrBindings() : $psrBindings;

        return $psrBindings ?? HttpPsrBindings::default();
    }

    /**
     * @return FormatterInterface<T>
     */
    public function getFormatter(): FormatterInterface
    {
        return $this->driverConfiguration->getTransactionConfiguration()->getFormatter();
    }

    public function getDatabase(): string
    {
        return $this->driverConfiguration->getSessionConfiguration()->getDatabase();
    }

    /**
     * @template U
     *
     * @param callable():FormatterInterface<U>|FormatterInterface<U> $formatter
     *
     * @return self<U>
     */
    public function withFormatter($formatter): self
    {
        $driverConfiguration = $this->driverConfiguration->withFormatter($formatter);

        return new self($driverConfiguration, $this->defaultDriver, $this->psrBindings);
    }

    /**
     * @param callable():(float|null)|float|null $timeout
     *
     * @return self<T>
     */
    public function withTransactionTimeout($timeout): self
    {
        $driverConfiguration = $this->driverConfiguration->withTransactionTimeout($timeout);

        return new self($driverConfiguration, $this->defaultDriver, $this->psrBindings);
    }

    /**
     * @param callable():(int|null)|int|null $fetchSize
     *
     * @return self<T>
     */
    public function withFetchSize($fetchSize): self
    {
        $driverConfiguration = $this->driverConfiguration->withFetchSize($fetchSize);

        return new self($driverConfiguration, $this->defaultDriver, $this->psrBindings);
    }

    /**
     * @param callable():(string|null)|string|null $defaultDriver
     *
     * @return self<T>
     */
    public function withDefaultDriver($defaultDriver): self
    {
        return new self($this->driverConfiguration, $defaultDriver, $this->psrBindings);
    }

    /**
     * @param callable():(AccessMode|null)|AccessMode|null $accessMode
     *
     * @return self<T>
     */
    public function withAccessMode($accessMode): self
    {
        $driverConfiguration = $this->driverConfiguration->withAccessMode($accessMode);

        return new self($driverConfiguration, $this->defaultDriver, $this->psrBindings);
    }

    /**
     * @param callable():(HttpPsrBindings|null)|HttpPsrBindings|null $bindings
     *
     * @return self<T>
     */
    public function withHttpPsrBindings($bindings): self
    {
        $driverConfiguration = $this->driverConfiguration->withHttpPsrBindings($bindings);

        return new self($driverConfiguration, $this->defaultDriver, $this->psrBindings);
    }

    /**
     * @param callable():(string|null)|string|null $userAgent
     *
     * @return self<T>
     */
    public function withUserAgent($userAgent): self
    {
        $driverConfiguration = $this->driverConfiguration->withUserAgent($userAgent);

        return new self($driverConfiguration, $this->defaultDriver, $this->psrBindings);
    }
}
