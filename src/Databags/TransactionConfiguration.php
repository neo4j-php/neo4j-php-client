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

/**
 * Configuration object for transactions.
 *
 * @psalm-immutable
 */
final class TransactionConfiguration
{
    public const DEFAULT_TIMEOUT = 60.0;
    public const DEFAULT_METADATA = '[]';

    /**
     * @param float|null                               $timeout  timeout in seconds
     * @param iterable<string, scalar|array|null>|null $metaData
     */
    public function __construct(
        private float|null $timeout = null,
        private iterable|null $metaData = null
    ) {}

    /**
     * @pure
     *
     * @param float|null                               $timeout  timeout in seconds
     * @param iterable<string, scalar|array|null>|null $metaData
     */
    public static function create(float|null $timeout = null, iterable|null $metaData = null): self
    {
        return new self($timeout, $metaData);
    }

    /**
     * @pure
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Get the configured transaction metadata.
     *
     * @return iterable<string, scalar|array|null>|null
     */
    public function getMetaData(): ?iterable
    {
        return $this->metaData;
    }

    /**
     * Get the configured transaction timeout in seconds.
     */
    public function getTimeout(): ?float
    {
        return $this->timeout;
    }

    /**
     * Creates a new transaction object with the provided timeout.
     *
     * @param float|null $timeout timeout in seconds
     */
    public function withTimeout(float|null $timeout): self
    {
        return new self($timeout, $this->metaData);
    }

    /**
     * Creates a new transaction object with the provided metadata.
     *
     * @param iterable<string, scalar|array|null>|null $metaData
     */
    public function withMetaData(iterable|null $metaData): self
    {
        return new self($this->timeout, $metaData);
    }

    /**
     * Creates a new transaction object by merging this one with the provided configuration.
     * The provided config overrides this config.
     */
    public function merge(?TransactionConfiguration $config): self
    {
        $tsxConfig = $this;
        $config ??= self::default();

        $metaData = $config->metaData;
        if ($metaData !== null) {
            $tsxConfig = $tsxConfig->withMetaData($metaData);
        }
        $timeout = $config->timeout;
        if ($timeout !== null) {
            $tsxConfig = $tsxConfig->withTimeout($timeout);
        }

        return $tsxConfig;
    }
}
