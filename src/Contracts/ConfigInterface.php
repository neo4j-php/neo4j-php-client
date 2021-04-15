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

namespace Laudis\Neo4j\Contracts;

/**
 * @deprecated
 */
interface ConfigInterface
{
    /**
     * @param string|callable():string $database
     *
     * @return static
     */
    public function withDatabase($database): self;

    /**
     * @param callable():bool|bool $routing
     *
     * @return static
     *
     * @deprecated enable auto routing by using the neo4j:// scheme as uri
     */
    public function withAutoRouting($routing): self;

    public function getDatabase(): string;

    public function hasAutoRouting(): bool;

    /**
     * @return static
     */
    public function mergeConfig(ConfigInterface $config): ConfigInterface;
}
