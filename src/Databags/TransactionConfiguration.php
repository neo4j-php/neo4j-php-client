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

final class TransactionConfiguration
{
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
     * @return callable():(iterable<string, scalar|array|null>|null)|iterable<string, scalar|array|null>|null
     */
    public function getMetaData()
    {
        return $this->metaData;
    }

    /**
     * Timeout in seconds.
     *
     * @return callable():(float|null)|float|null
     */
    public function getTimeout()
    {
        return $this->timeout;
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
