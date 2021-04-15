<?php

/*
 * This file is part of the Laudis Neo4j package.
 *
 * (c) Laudis technologies <http://laudis.tech>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Laudis\Neo4j\Contracts;

use Laudis\Neo4j\Databags\HttpPsrBindings;
use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\StaticTransactionConfiguration;

/**
 * @template T
 */
interface DriverConfigurationInterface
{
    public const DEFAULT_VERSION = 'v2.0.0-alpha';
    public const DEFAULT_USER_AGENT = 'LaudisNeo4j/'.self::DEFAULT_VERSION;

    public function getSessionConfiguration(): SessionConfiguration;

    /**
     * @return StaticTransactionConfiguration<T>
     */
    public function getTransactionConfiguration(): StaticTransactionConfiguration;

    public function getUserAgent(): string;

    /**
     * @param callable():(string|null)|string|null $userAgent
     *
     * @return self<T>
     */
    public function withUserAgent($userAgent): self;

    /**
     * @return self<T>
     */
    public function withSessionConfiguration(?SessionConfiguration $configuration): self;

    /**
     * @template U
     *
     * @param StaticTransactionConfiguration<U> $configuration
     *
     * @return self<U>
     */
    public function withTransactionConfiguration(StaticTransactionConfiguration $configuration): self;

    /**
     * @param callable():(\Laudis\Neo4j\Databags\HttpPsrBindings|null)|\Laudis\Neo4j\Databags\HttpPsrBindings|null $bindings
     *
     * @return self<T>
     */
    public function withHttpPsrBindings($bindings): self;

    /**
     * @param callable():(\Laudis\Neo4j\Enum\AccessMode|null)|\Laudis\Neo4j\Enum\AccessMode|null $accessMode
     *
     * @return self<T>
     */
    public function withAccessMode($accessMode): self;

    /**
     * @param callable():(int|null)|int|null $fetchSize
     *
     * @return self<T>
     */
    public function withFetchSize($fetchSize): self;

    /**
     * @param callable():(float|null)|float|null $timeout
     *
     * @return self<T>
     */
    public function withTransactionTimeout($timeout): self;

    /**
     * @template U
     *
     * @param callable():FormatterInterface<U>|FormatterInterface<U> $formatter
     *
     * @return self<U>
     */
    public function withFormatter($formatter): self;

    /**
     * @param callable():(string|null)|string|null $database
     *
     * @return self<T>
     */
    public function withDatabase($database): self;

    public function getHttpPsrBindings(): HttpPsrBindings;
}
