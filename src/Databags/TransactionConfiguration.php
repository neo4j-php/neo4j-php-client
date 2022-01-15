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

use function is_callable;

/**
 * Configuration object for transactions.
 *
 * @psalm-immutable
 */
final class TransactionConfiguration
{
    public const DEFAULT_TIMEOUT = 60.0;
    public const DEFAULT_METADATA = '[]';

    /** @var pure-callable():(float|null)|float|null */
    private $timeout;
    /** @var pure-callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null */
    private $metaData;

    /**
     * @param pure-callable():(float|null)|float|null                                                             $timeout  timeout in seconds
     * @param pure-callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null $metaData
     */
    public function __construct($timeout = null, $metaData = null)
    {
        $this->timeout = $timeout;
        $this->metaData = $metaData;
    }

    /**
     * @pure
     *
     * @param pure-callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null $metaData
     * @param pure-callable():(float|null)|float|null                                                             $timeout  timeout in seconds
     */
    public static function create($timeout = null, $metaData = null): self
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
     * @return iterable<string, scalar|array|null>
     */
    public function getMetaData(): iterable
    {
        $tbr = $this->metaData;
        if (is_callable($tbr)) {
            $tbr = $tbr();
        }

        return $tbr ?? [];
    }

    /**
     * Get the configured transaction timeout in seconds.
     */
    public function getTimeout(): float
    {
        $tbr = $this->timeout;
        if (is_callable($tbr)) {
            $tbr = $tbr();
        }

        return $tbr ?? self::DEFAULT_TIMEOUT;
    }

    /**
     * Creates a new transaction object with the provided timeout.
     *
     * @param pure-callable():(float|null)|float|null $timeout timeout in seconds
     */
    public function withTimeout($timeout): self
    {
        return new self($timeout, $this->metaData);
    }

    /**
     * Creates a new transaction object with the provided metadata.
     *
     * @param pure-callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null $metaData
     */
    public function withMetaData($metaData): self
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
        if ($metaData) {
            $tsxConfig = $tsxConfig->withMetaData($metaData);
        }
        $timeout = $config->timeout;
        if ($timeout) {
            $tsxConfig = $tsxConfig->withTimeout($timeout);
        }

        return $tsxConfig;
    }
}
