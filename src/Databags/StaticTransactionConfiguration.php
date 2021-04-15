<?php

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
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Formatter\BasicFormatter;

/**
 * @template T
 */
final class StaticTransactionConfiguration
{
    public const DEFAULT_TIMEOUT = 15.0;
    public const DEFAULT_METADATA = '[]';
    public const DEFAULT_FORMATTER = BasicFormatter::class;

    /** @var callable():(FormatterInterface<T>)|FormatterInterface<T> */
    private $formatter;
    private TransactionConfiguration $config;

    /**
     * @param callable():FormatterInterface<T>|FormatterInterface<T> $formatter
     */
    public function __construct($formatter, ?TransactionConfiguration $configuration = null)
    {
        $this->formatter = $formatter;
        $this->config = $configuration ?? new TransactionConfiguration();
    }

    /**
     * @return self<Vector<Map<string, scalar|array|null>>>
     */
    public static function default(): self
    {
        return new self(new BasicFormatter());
    }

    /**
     * @template U
     *
     * @param callable():(float|null)|float|null                                                             $timeout   timeout in seconds
     * @param callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null $metaData
     * @param callable():FormatterInterface<U>|FormatterInterface<U>                                         $formatter
     *
     * @return self<U>
     */
    public static function create($formatter, $timeout = null, $metaData = null): self
    {
        return new self($formatter, TransactionConfiguration::create($timeout, $metaData));
    }

    /**
     * @return iterable<string, scalar|array|null>
     */
    public function getMetaData(): iterable
    {
        $metaData = $this->config->getMetaData();
        if (is_callable($metaData)) {
            $metaData = $metaData();
        }

        return $metaData ?? [];
    }

    /**
     * Timeout in seconds.
     */
    public function getTimeout(): float
    {
        $timeout = $this->config->getTimeout();
        if (is_callable($timeout)) {
            $timeout = $timeout();
        }

        return $timeout ?? self::DEFAULT_TIMEOUT;
    }

    /**
     * @return FormatterInterface<T>
     */
    public function getFormatter(): FormatterInterface
    {
        return is_callable($this->formatter) ? call_user_func($this->formatter) : $this->formatter;
    }

    /**
     * @param callable():(float|null)|float|null $timeout timeout in seconds
     *
     * @return self<T>
     */
    public function withTimeout($timeout): self
    {
        return new self($this->formatter, $this->config->withTimeout($timeout));
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
        return new self($formatter, $this->config);
    }

    /**
     * @param callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null $metaData
     *
     * @return self<T>
     */
    public function withMetaData($metaData): self
    {
        return new self($this->formatter, $this->config->withMetaData($metaData));
    }

    /**
     * @return self<T>
     */
    public function merge(?TransactionConfiguration $config): self
    {
        $tsxConfig = $this;
        $config ??= TransactionConfiguration::create();

        $metaData = $config->getMetaData();
        if ($metaData) {
            $tsxConfig = $tsxConfig->withMetaData($metaData);
        }
        $timeout = $config->getTimeout();
        if ($timeout) {
            $tsxConfig = $tsxConfig->withTimeout($timeout);
        }

        return $tsxConfig;
    }
}
