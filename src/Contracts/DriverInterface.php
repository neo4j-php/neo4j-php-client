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

use Laudis\Neo4j\Databags\SessionConfiguration;
use Laudis\Neo4j\Databags\StaticTransactionConfiguration;
use Laudis\Neo4j\Databags\TransactionConfiguration;

/**
 * @template T
 */
interface DriverInterface
{
    /**
     * @return mixed
     */
    public function acquireConnection(SessionConfiguration $configuration);

    /**
     * @return SessionInterface<T>
     */
    public function createSession(?SessionConfiguration $config = null): SessionInterface;

    /**
     * @param callable():string|string $userAgent
     *
     * @return static<T>
     */
    public function withUserAgent($userAgent): self;

    /**
     * @return static<T>
     */
    public function withSessionConfiguration(?SessionConfiguration $configuration): self;

    /**
     * @return static<T>
     */
    public function withTransactionConfiguration(?TransactionConfiguration $configuration): self;

    /**
     * @template V
     *
     * @param DriverConfigurationInterface<V> $configuration
     *
     * @return static<V>
     */
    public function withConfiguration(DriverConfigurationInterface $configuration): self;

    /**
     * @return StaticTransactionConfiguration<T>
     */
    public function getTransactionConfiguration(): StaticTransactionConfiguration;

    public function getSessionConfiguration(): SessionConfiguration;

    /**
     * @template V
     *
     * @param callable():FormatterInterface<V>|FormatterInterface<V> $formatter
     *
     * @return static<V>
     */
    public function withFormatter($formatter): self;

    /**
     * @param callable():(float|null)|float|null $timeout
     *
     * @return static<T>
     */
    public function withTransactionTimeout($timeout): self;

    /**
     * @param callable():(string|null)|string|null $database
     *
     * @return static<T>
     */
    public function withDatabase($database): self;

    /**
     * @param callable():(int|null)|int|null $fetchSize
     *
     * @return static<T>
     */
    public function withFetchSize($fetchSize): self;

    /**
     * @param callable():(\Laudis\Neo4j\Enum\AccessMode|null)|\Laudis\Neo4j\Enum\AccessMode|null $accessMode
     *
     * @return static<T>
     */
    public function withAccessMode($accessMode): self;

    /**
     * @return DriverConfigurationInterface<T>
     */
    public function getConfiguration(): DriverConfigurationInterface;
}
