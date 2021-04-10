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

final class TransactionConfig
{
    private ?float $timeout;
    /** @var Map<string, mixed> */
    private Map $metaData;

    /**
     * @param float|null         $timeout  timeout in seconds
     * @param Map<string, mixed> $metaData
     */
    public function __construct(?float $timeout, Map $metaData = null)
    {
        $this->timeout = $timeout;
        $this->metaData = $metaData ?? new Map();
    }

    public static function default(): TransactionConfig
    {
        return new self(null);
    }

    /**
     * @return Map<string, mixed>
     */
    public function getMetaData(): Map
    {
        return $this->metaData;
    }

    /**
     * Time in seconds.
     */
    public function getTimeout(): ?float
    {
        return $this->timeout;
    }
}
