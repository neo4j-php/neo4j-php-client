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

namespace Laudis\Neo4j\Databags;

use function call_user_func;
use Composer\InstalledVersions;
use function function_exists;
use function is_callable;
use Laudis\Neo4j\Common\Cache;
use Psr\SimpleCache\CacheInterface;
use function sprintf;

/**
 * Configuration object for the driver.
 *
 * @psalm-immutable
 */
final class DriverConfiguration
{
    public const DEFAULT_USER_AGENT = 'neo4j-php-client/%s';
    public const DEFAULT_POOL_SIZE = 0x2F;
    public const DEFAULT_CACHE_IMPLEMENTATION = Cache::class;
    public const DEFAULT_ACQUIRE_CONNECTION_TIMEOUT = 2.0;

    private ?string $userAgent;
    /** @var pure-callable():(HttpPsrBindings|null)|HttpPsrBindings|null */
    private $httpPsrBindings;
    private SslConfiguration $sslConfig;
    private ?int $maxPoolSize;
    /** @var pure-callable():(CacheInterface|null)|CacheInterface|null */
    private $cache;
    /** @var ?float */
    private ?float $acquireConnectionTimeout;

    /**
     * @param pure-callable():(HttpPsrBindings|null)|HttpPsrBindings|null $httpPsrBindings
     * @param pure-callable():(CacheInterface|null)|CacheInterface|null $cache
     */
    public function __construct(?string $userAgent, $httpPsrBindings, SslConfiguration $sslConfig, ?int $maxPoolSize, $cache, ?float $acquireConnectionTimeout)
    {
        $this->userAgent = $userAgent;
        $this->httpPsrBindings = $httpPsrBindings;
        $this->sslConfig = $sslConfig;
        $this->maxPoolSize = $maxPoolSize;
        $this->cache = $cache;
        $this->acquireConnectionTimeout = $acquireConnectionTimeout;
    }

    /**
     * @param pure-callable():(HttpPsrBindings|null)|HttpPsrBindings|null $httpPsrBindings
     *
     * @pure
     */
    public static function create(?string $userAgent, $httpPsrBindings, SslConfiguration $sslConfig, int $maxPoolSize, CacheInterface $cache, float $acquireConnectionTimeout): self
    {
        return new self($userAgent, $httpPsrBindings, $sslConfig, $maxPoolSize, $cache, $acquireConnectionTimeout);
    }

    /**
     * Creates a default configuration with a user agent based on the driver version
     * and HTTP PSR implementation auto detected from the environment.
     *
     * @pure
     */
    public static function default(): self
    {
        /** @psalm-suppress ImpureMethodCall */
        return new self(self::DEFAULT_USER_AGENT, HttpPsrBindings::default(), SslConfiguration::default(), self::DEFAULT_POOL_SIZE, Cache::getInstance(), self::DEFAULT_ACQUIRE_CONNECTION_TIMEOUT);
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

    public function getMaxPoolSize(): ?int
    {
        return $this->maxPoolSize ?? self::DEFAULT_POOL_SIZE;
    }

    public function withMaxPoolSize(?int $maxPoolSize): self
    {
        $tbr = clone $this;
        $tbr->maxPoolSize = $maxPoolSize;

        return $tbr;
    }

    /**
     * @param pure-callable():(CacheInterface|null)|CacheInterface|null $cache
     */
    public function withCache($cache): self
    {
        $tbr = clone $this;
        $tbr->cache = $cache;

        return $tbr;
    }

    public function getCache(): CacheInterface
    {
        $cache = (is_callable($this->cache)) ? call_user_func($this->cache) : $this->cache;

        /** @psalm-suppress ImpureMethodCall */
        return $cache ?? Cache::getInstance();
    }

    public function getAcquireConnectionTimeout(): ?float
    {
        return $this->acquireConnectionTimeout;
    }

    public function withAcquireConnectionTimeout(?float $acquireConnectionTimeout): self
    {
        $tbr = clone $this;
        $tbr->acquireConnectionTimeout = $acquireConnectionTimeout;

        return $tbr;
    }
}
