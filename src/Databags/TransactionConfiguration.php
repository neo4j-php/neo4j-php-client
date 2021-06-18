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
use function is_callable;

final class TransactionConfiguration
{
    public const DEFAULT_TIMEOUT = 15.0;
    public const DEFAULT_METADATA = '[]';

    /** @var callable():(float|null)|float|null */
    private $timeout;
    /** @var callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null */
    private $metaData;

    /**
     * @param callable():(float|null)|float|null                                                             $timeout  timeout in seconds
     * @param callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null $metaData
     */
    public function __construct($timeout = null, $metaData = null)
    {
        $this->timeout = $timeout;
        $this->metaData = $metaData;
    }

    /**
     * @param callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null $metaData
     * @param callable():(float|null)|float|null                                                             $timeout  timeout in seconds
     */
    public static function create($timeout = null, $metaData = null): self
    {
        return new self($timeout, $metaData);
    }

    public static function default(): self
    {
        return new self();
    }

    /**
     * @return iterable<string, scalar|array|null>
     */
    public function getMetaData(): iterable
    {
        $tbr = $this->metaData;
        if (is_callable($tbr)) {
            $tbr = $tbr();
        }

        return $tbr ?? new Map();
    }

    /**
     * Timeout in seconds.
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
     * @param callable():(float|null)|float|null $timeout timeout in seconds
     */
    public function withTimeout($timeout): self
    {
        return new self($timeout, $this->metaData);
    }

    /**
     * @param callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null $metaData
     */
    public function withMetaData($metaData): self
    {
        return new self($this->timeout, $metaData);
    }

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
