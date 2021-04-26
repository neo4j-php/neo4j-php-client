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
use Laudis\Neo4j\Contracts\FormatterInterface;
use Laudis\Neo4j\Formatter\BasicFormatter;

/**
 * @template T
 */
final class StaticTransactionConfiguration extends TransactionConfiguration
{
    public const DEFAULT_FORMATTER = BasicFormatter::class;

    /** @var callable():(FormatterInterface<T>)|FormatterInterface<T> */
    private $formatter;

    /**
     * @param callable():FormatterInterface<T>|FormatterInterface<T>                                         $formatter
     * @param callable():(float|null)|float|null                                                             $timeout   timeout in seconds
     * @param callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null $metaData
     */
    public function __construct($formatter, $timeout = null, $metaData = null)
    {
        $this->formatter = $formatter;
        parent::__construct($timeout, $metaData);
    }

    /**
     * @return self<Vector<Map<string, scalar|array|null>>>
     */
    public static function default(): self
    {
        return new self(new BasicFormatter());
    }

    /**
     * @param callable():(float|null)|float|null                                                             $timeout  timeout in seconds
     * @param callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null $metaData
     *
     * @return self<Vector<Map<string, array|scalar|null>>>
     */
    public static function create($timeout = null, $metaData = null): self
    {
        return self::default()->withTimeout($timeout)->withMetaData($metaData);
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
        return new self($this->formatter, $timeout, $this->metaData);
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
        return new self($formatter, $this->timeout, $this->metaData);
    }

    /**
     * @param callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null $metaData
     *
     * @return self<T>
     */
    public function withMetaData($metaData): self
    {
        return new self($this->formatter, $this->timeout, $metaData);
    }

    /**
     * @return self<T>
     */
    public function merge(?TransactionConfiguration $config): self
    {
        $tsxConfig = $this;
        $config ??= TransactionConfiguration::create();

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
