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
use Composer\InstalledVersions;
use function function_exists;
use function is_callable;
use function sprintf;

/**
 * Configuration object for the driver.
 *
 * @psalm-immutable
 */
final class DriverConfiguration
{
    public const DEFAULT_USER_AGENT = 'neo4j-php-client/%s';

    private ?string $userAgent;
    /** @var pure-callable():(HttpPsrBindings|null)|HttpPsrBindings|null */
    private $httpPsrBindings;
    private SslConfiguration $sslConfig;

    /**
     * @param pure-callable():(HttpPsrBindings|null)|HttpPsrBindings|null $httpPsrBindings
     */
    public function __construct(?string $userAgent, $httpPsrBindings, SslConfiguration $sslConfig)
    {
        $this->userAgent = $userAgent;
        $this->httpPsrBindings = $httpPsrBindings;
        $this->sslConfig = $sslConfig;
    }

    /**
     * @param pure-callable():(HttpPsrBindings|null)|HttpPsrBindings|null $httpPsrBindings
     *
     * @pure
     */
    public static function create(?string $userAgent, $httpPsrBindings, SslConfiguration $sslConfig): self
    {
        return new self($userAgent, $httpPsrBindings, $sslConfig);
    }

    /**
     * Creates a default configuration with a user agent based on the driver version
     * and HTTP PSR implementation auto detected from the environment.
     *
     * @pure
     */
    public static function default(): self
    {
        return new self(self::DEFAULT_USER_AGENT, HttpPsrBindings::default(), SslConfiguration::default());
    }

    public function getUserAgent(): string
    {
        if ($this->userAgent === null) {
            if (function_exists('InstalledVersions::getPrettyVersion')) {
                /** @psalm-suppress ImpureMethodCall */
                $version = InstalledVersions::getPrettyVersion('laudis/neo4j-php-client') ?? 'provided/replaced';
            } else {
                $version = '2';
            }

            return sprintf(self::DEFAULT_USER_AGENT, $version);
        }

        return $this->userAgent;
    }

    /**
     * Creates a new configuration with the provided user agent.
     *
     * @param string|null $userAgent
     */
    public function withUserAgent($userAgent): self
    {
        $tbr = clone $this;
        $tbr->userAgent = $userAgent;

        return $tbr;
    }

    /**
     * Creates a new configuration with the provided bindings.
     *
     * @param pure-callable():(HttpPsrBindings|null)|HttpPsrBindings|null $bindings
     */
    public function withHttpPsrBindings($bindings): self
    {
        $tbr = clone $this;
        $tbr->httpPsrBindings = $bindings;

        return $tbr;
    }

    public function withSslConfiguration(SslConfiguration $config): self
    {
        $tbr = clone $this;
        $tbr->sslConfig = $config;

        return $tbr;
    }

    public function getSslConfiguration(): SslConfiguration
    {
        return $this->sslConfig;
    }

    public function getHttpPsrBindings(): HttpPsrBindings
    {
        $bindings = (is_callable($this->httpPsrBindings)) ? call_user_func($this->httpPsrBindings) : $this->httpPsrBindings;

        return $bindings ?? HttpPsrBindings::default();
    }
}
