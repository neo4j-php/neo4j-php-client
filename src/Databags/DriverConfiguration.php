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

use function call_user_func;
use Ds\Map;
use Ds\Vector;
use function is_callable;
use Laudis\Neo4j\Contracts\DriverConfigurationInterface;

/**
 * @template T
 *
 * @implements DriverConfigurationInterface<T>
 */
final class DriverConfiguration implements DriverConfigurationInterface
{
    private SessionConfiguration $sessionConfiguration;
    /** @var StaticTransactionConfiguration<T> */
    private StaticTransactionConfiguration $transactionConfiguration;
    /** @var callable():(string|null)|string|null */
    private $userAgent;
    /** @var callable():(HttpPsrBindings|null)|HttpPsrBindings|null */
    private $httpPsrBindings;

    /**
     * @param StaticTransactionConfiguration<T>                      $transactionConfiguration
     * @param callable():(string|null)|string|null                   $userAgent
     * @param callable():(HttpPsrBindings|null)|HttpPsrBindings|null $httpPsrBindings
     */
    public function __construct(
        SessionConfiguration $sessionConfiguration,
        StaticTransactionConfiguration $transactionConfiguration,
        $userAgent,
        $httpPsrBindings
    ) {
        $this->sessionConfiguration = $sessionConfiguration;
        $this->transactionConfiguration = $transactionConfiguration;
        $this->userAgent = $userAgent;
        $this->httpPsrBindings = $httpPsrBindings;
    }

    /**
     * @template U
     *
     * @param StaticTransactionConfiguration<U>                      $transactionConfiguration
     * @param callable():(string|null)|string|null                   $userAgent
     * @param callable():(HttpPsrBindings|null)|HttpPsrBindings|null $httpPsrBindings
     *
     * @return self<U>
     */
    public static function create(SessionConfiguration $sessionConfiguration, StaticTransactionConfiguration $transactionConfiguration, $userAgent, $httpPsrBindings): self
    {
        return new self($sessionConfiguration, $transactionConfiguration, $userAgent, $httpPsrBindings);
    }

    /**
     * @return self<Vector<Map<string, scalar|array|null>>>
     */
    public static function default(): self
    {
        return new self(
            SessionConfiguration::create(),
            StaticTransactionConfiguration::default(),
            DriverConfigurationInterface::DEFAULT_USER_AGENT,
            HttpPsrBindings::default()
        );
    }

    public function getSessionConfiguration(): SessionConfiguration
    {
        return $this->sessionConfiguration;
    }

    public function getTransactionConfiguration(): StaticTransactionConfiguration
    {
        return $this->transactionConfiguration;
    }

    public function getUserAgent(): string
    {
        $userAgent = (is_callable($this->userAgent)) ? call_user_func($this->userAgent) : $this->userAgent;

        return $userAgent ?? DriverConfigurationInterface::DEFAULT_USER_AGENT;
    }

    public function withUserAgent($userAgent): self
    {
        return new self($this->sessionConfiguration, $this->transactionConfiguration, $userAgent, $this->httpPsrBindings);
    }

    public function withSessionConfiguration(?SessionConfiguration $configuration): self
    {
        return new self($configuration ?? SessionConfiguration::default(), $this->transactionConfiguration, $this->userAgent, $this->httpPsrBindings);
    }

    public function withTransactionConfiguration(StaticTransactionConfiguration $configuration): self
    {
        return new self($this->sessionConfiguration, $configuration, $this->userAgent, $this->httpPsrBindings);
    }

    public function withHttpPsrBindings($bindings): DriverConfigurationInterface
    {
        return new self($this->sessionConfiguration, $this->transactionConfiguration, $this->userAgent, $bindings);
    }

    public function withAccessMode($accessMode): DriverConfigurationInterface
    {
        return new self($this->sessionConfiguration->withAccessMode($accessMode), $this->transactionConfiguration, $this->userAgent, $this->httpPsrBindings);
    }

    public function withFetchSize($fetchSize): DriverConfigurationInterface
    {
        return new self($this->sessionConfiguration->withFetchSize($fetchSize), $this->transactionConfiguration, $this->userAgent, $this->httpPsrBindings);
    }

    public function withTransactionTimeout($timeout): DriverConfigurationInterface
    {
        return new self($this->sessionConfiguration, $this->transactionConfiguration->withTimeout($timeout), $this->userAgent, $this->httpPsrBindings);
    }

    public function withFormatter($formatter): DriverConfigurationInterface
    {
        return new self($this->sessionConfiguration, $this->transactionConfiguration->withFormatter($formatter), $this->userAgent, $this->httpPsrBindings);
    }

    public function withDatabase($database): DriverConfigurationInterface
    {
        return new self($this->sessionConfiguration->withDatabase($database), $this->transactionConfiguration, $this->userAgent, $this->httpPsrBindings);
    }

    public function getHttpPsrBindings(): HttpPsrBindings
    {
        $bindings = (is_callable($this->httpPsrBindings)) ? call_user_func($this->httpPsrBindings) : $this->httpPsrBindings;

        return $bindings ?? HttpPsrBindings::default();
    }
}
