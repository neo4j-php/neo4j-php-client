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

class TransactionConfiguration
{
    public const DEFAULT_TIMEOUT = 15.0;
    public const DEFAULT_METADATA = '[]';

    /** @var callable():(float|null)|float|null */
    protected $timeout;
    /** @var callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null */
    protected $metaData;

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
        return  (is_callable($this->metaData) ? call_user_func($this->metaData) : $this->metaData) ?? new Map();
    }

    /**
     * Timeout in seconds.
     */
    public function getTimeout(): float
    {
        return (is_callable($this->timeout) ? call_user_func($this->timeout) : $this->timeout) ?? self::DEFAULT_TIMEOUT;
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
}
